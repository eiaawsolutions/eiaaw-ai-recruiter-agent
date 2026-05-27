<?php

namespace App\Services\Outreach;

use App\Models\OutreachMessage;
use App\Services\Agents\SchedulingAgent;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Log;

class ReplyParser
{
    public function __construct(
        private readonly SchedulingAgent $scheduler,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * Apply an inbound reply to an outreach message:
     *  - mark the outreach as `replied`
     *  - move the candidate to `replied` stage
     *  - record provider events
     *  - if the reply looks like availability text, trigger SchedulingAgent
     *  - emit outreach.replied (+ interview.proposed when slots are generated)
     */
    public function ingestReply(OutreachMessage $outreach, array $payload): void
    {
        $body = $this->bestBody($payload);
        $sender = (string) ($payload['sender'] ?? $payload['from'] ?? '');

        $events = $outreach->provider_events ?? [];
        $events[] = ['type' => 'replied', 'at' => now()->toIso8601String(), 'sender' => $sender];

        $outreach->forceFill([
            'status'          => 'replied',
            'replied_at'      => now(),
            'provider_events' => $events,
        ])->saveQuietly();

        $candidate = $outreach->candidate;
        if ($candidate && ! in_array($candidate->stage, ['interview_scheduled', 'shortlisted', 'hired'], true)) {
            $candidate->update(['stage' => 'replied']);
        }

        $this->webhooks->dispatch('outreach.replied', [
            'outreach_id'  => $outreach->public_id,
            'candidate_id' => $candidate?->public_id,
            'reply_excerpt' => mb_substr($body, 0, 280),
        ]);

        if ($candidate && $this->looksLikeAvailability($body)) {
            try {
                $slots = $this->scheduler->proposeSlots($candidate, $body);
                if (count($slots) > 0) {
                    $this->webhooks->dispatch('interview.proposed', [
                        'candidate_id' => $candidate->public_id,
                        'slots'        => array_map(fn ($s) => [
                            'id'        => $s->public_id,
                            'starts_at' => $s->starts_at->toIso8601String(),
                            'ends_at'   => $s->ends_at->toIso8601String(),
                            'status'    => $s->status,
                        ], $slots),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('ReplyParser: scheduling agent failed', [
                    'outreach_id' => $outreach->public_id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    private function bestBody(array $payload): string
    {
        // Cleanest sources first:
        //  - Mailgun's `stripped-text` removes the quoted prior message
        //  - Resend's `data.text` is the plain-text body of the received email
        $body = $payload['stripped-text']
            ?? $payload['body-plain']
            ?? $payload['data']['text']
            ?? $payload['data']['html']
            ?? $payload['body-html']
            ?? '';

        if (is_string($body) && str_contains($body, '<') && str_contains($body, '>')) {
            $body = strip_tags($body);
        }
        return trim((string) $body);
    }

    /**
     * Cheap heuristic: keep the agent call rare. We only spin it up when the
     * text mentions availability, a day name, a time, or scheduling words.
     */
    public function looksLikeAvailability(string $text): bool
    {
        // Bound the input before regex / strtolower — defeats ReDoS-style
        // pathological inputs from inbound email bodies. 16KB is far more than
        // any real availability text needs.
        $text = mb_substr($text, 0, 16_000);

        $needles = [
            'available', 'availability', 'free', 'schedule', 'calendar', 'call',
            'meet', 'chat', 'speak',
            'monday','tuesday','wednesday','thursday','friday','saturday','sunday',
            'mon','tue','wed','thu','fri','sat','sun',
            'tomorrow', 'next week', 'this week',
            'am', 'pm', ':00', ':30',
        ];
        $t = strtolower($text);
        foreach ($needles as $n) {
            if (str_contains($t, $n)) return true;
        }
        return (bool) preg_match('/\b\d{1,2}(?:[:.]\d{2})?\s*(?:am|pm|h|hrs)?\b/i', $text);
    }
}
