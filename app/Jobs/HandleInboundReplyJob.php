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

            // Match the outreach: by header, then by recipient tag.
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
        // 1. X-EIAAW-Outreach-Id custom header (we set this on outbound)
        $candidates = [
            $payload['X-Eiaaw-Outreach-Id'] ?? null,
            $payload['X-EIAAW-Outreach-Id'] ?? null,
            $payload['x-eiaaw-outreach-id'] ?? null,
        ];
        foreach (array_filter($candidates) as $publicId) {
            $m = OutreachMessage::query()->withoutGlobalScopes()->where('public_id', $publicId)->first();
            if ($m) return $m;
        }

        // 2. In-Reply-To header → provider_message_id (the Mailgun id we stored)
        $inReplyTo = trim((string) ($payload['In-Reply-To'] ?? $payload['in-reply-to'] ?? ''), '<>');
        if ($inReplyTo !== '') {
            $m = OutreachMessage::query()->withoutGlobalScopes()
                ->where('provider_message_id', $inReplyTo)
                ->orWhere('provider_message_id', "<{$inReplyTo}>")
                ->first();
            if ($m) return $m;
        }

        // 3. References header (chain of message IDs)
        $references = (string) ($payload['References'] ?? $payload['references'] ?? '');
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
        $sender = strtolower(trim((string) ($payload['sender'] ?? $payload['from'] ?? '')));
        if ($sender !== '') {
            return OutreachMessage::query()->withoutGlobalScopes()
                ->where('to_address', $sender)
                ->where('status', 'sent')
                ->latest('sent_at')
                ->first();
        }

        return null;
    }
}
