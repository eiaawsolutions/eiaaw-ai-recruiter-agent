<?php

namespace App\Jobs;

use App\Models\InboundWebhookEvent;
use App\Models\OutreachMessage;
use App\Services\Outreach\ReplyParser;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleInboundReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 20;
    public int $timeout = 60;

    public function __construct(public int $inboundEventId) {}

    public function handle(ReplyParser $parser): void
    {
        $event = InboundWebhookEvent::query()->find($this->inboundEventId);
        if (! $event || $event->processed_at) return;

        try {
            $payload = $event->payload ?? [];

            $outreach = $this->matchOutreach($payload);
            if (! $outreach) {
                $event->update(['processing_error' => 'no_matching_outreach', 'processed_at' => now()]);
                return;
            }

            TenantContext::bindById($outreach->tenant_id);

            $parser->ingestReply($outreach, $payload);

            $event->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            $event->update([
                'processing_error' => mb_substr($e->getMessage(), 0, 1000),
                'processed_at'     => now(),
            ]);
            throw $e;
        } finally {
            TenantContext::clear();
        }
    }

    private function matchOutreach(array $payload): ?OutreachMessage
    {
        $h = $this->headersIndex($payload);

        // 1. X-EIAAW-Outreach-Id custom header (we set this on outbound)
        $publicId = $h['x-eiaaw-outreach-id'] ?? null;
        if ($publicId) {
            $m = OutreachMessage::query()->withoutGlobalScopes()->where('public_id', $publicId)->first();
            if ($m) return $m;
        }

        // 2. In-Reply-To header → provider_message_id
        $inReplyTo = trim((string) ($h['in-reply-to'] ?? ''), '<>');
        if ($inReplyTo !== '') {
            $m = OutreachMessage::query()->withoutGlobalScopes()
                ->where('provider_message_id', $inReplyTo)
                ->orWhere('provider_message_id', "<{$inReplyTo}>")
                ->first();
            if ($m) return $m;
        }

        // 3. References header (chain of message IDs)
        $references = (string) ($h['references'] ?? '');
        if ($references !== '') {
            preg_match_all('/<([^>]+)>/', $references, $m);
            foreach ($m[1] ?? [] as $id) {
                $row = OutreachMessage::query()->withoutGlobalScopes()
                    ->where('provider_message_id', $id)
                    ->orWhere('provider_message_id', "<{$id}>")
                    ->first();
                if ($row) return $row;
            }
        }

        // 4. Sender match — last resort, latest outreach to this address
        $sender = strtolower(trim($this->senderAddress($payload)));
        if ($sender !== '') {
            return OutreachMessage::query()->withoutGlobalScopes()
                ->where('to_address', $sender)
                ->where('status', 'sent')
                ->latest('sent_at')
                ->first();
        }

        return null;
    }

    /**
     * Normalize headers from either:
     *  - Resend: payload.data.headers = [{name, value}, ...]
     *  - Mailgun-style flat keys: payload['In-Reply-To'], etc.
     * Returns lowercase-keyed associative array.
     */
    private function headersIndex(array $payload): array
    {
        $out = [];

        // Resend shape
        $headers = $payload['data']['headers'] ?? null;
        if (is_array($headers)) {
            foreach ($headers as $hdr) {
                if (isset($hdr['name'], $hdr['value'])) {
                    $out[strtolower((string) $hdr['name'])] = (string) $hdr['value'];
                }
            }
        }

        // Flat keys (Mailgun-style or test fixtures)
        foreach ($payload as $k => $v) {
            if (is_string($v)) {
                $out[strtolower((string) $k)] = $v;
            }
        }

        return $out;
    }

    private function senderAddress(array $payload): string
    {
        // Resend
        $from = $payload['data']['from'] ?? null;
        if (is_string($from)) {
            if (preg_match('/<([^>]+)>/', $from, $m)) return $m[1];
            return $from;
        }
        // Mailgun-style fallback
        return (string) ($payload['sender'] ?? $payload['from'] ?? '');
    }
}
