<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\HandleInboundReplyJob;
use App\Models\InboundWebhookEvent;
use App\Models\OutreachMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resend webhook endpoint. Handles both:
 *   - Email lifecycle events: email.delivered, email.opened, email.clicked,
 *     email.bounced, email.complained, email.delivery_delayed
 *   - Inbound replies (Resend Inbound): email.received
 *
 * Signature already validated by VerifyResendSignature middleware.
 */
class ResendInboundController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $payload = $request->all();
        $type    = (string) ($payload['type'] ?? '');
        $eventId = (string) ($payload['data']['email_id'] ?? $payload['data']['id'] ?? '');

        $event = InboundWebhookEvent::updateOrCreate(
            ['provider' => 'resend', 'event_id' => $eventId !== '' ? "{$type}:{$eventId}" : null],
            [
                'event_type'      => $type,
                'payload'         => $payload,
                'signature_valid' => true,
                'processed_at'    => now(),
            ]
        );

        if ($type === 'email.received') {
            // Inbound reply — hand to the reply parser job for matching +
            // SchedulingAgent triggering.
            HandleInboundReplyJob::dispatch($event->id);
        } else {
            // Lifecycle event — update the outreach message in-place.
            $this->applyLifecycleEvent($type, $payload);
        }

        return response()->json(['ok' => true]);
    }

    private function applyLifecycleEvent(string $type, array $payload): void
    {
        $emailId = (string) ($payload['data']['email_id'] ?? $payload['data']['id'] ?? '');
        if ($emailId === '') return;

        $msg = OutreachMessage::query()
            ->withoutGlobalScopes()
            ->where('provider_message_id', $emailId)
            ->first();
        if (! $msg) return;

        $events = $msg->provider_events ?? [];
        $events[] = ['type' => $type, 'at' => now()->toIso8601String()];
        $update = ['provider_events' => $events];

        match ($type) {
            'email.delivered'        => $update['status'] = 'delivered',
            'email.opened'           => $update['status'] = 'opened',
            'email.clicked'          => $update['status'] = 'clicked',
            'email.bounced',
            'email.complained'       => $update['status'] = 'bounced',
            'email.delivery_delayed' => null,
            default                  => null,
        };

        $msg->forceFill($update)->saveQuietly();
    }
}
