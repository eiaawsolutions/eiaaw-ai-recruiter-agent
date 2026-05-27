<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\InboundWebhookEvent;
use App\Models\OutreachMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailgunWebhookController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $event   = (array) $request->input('event-data', []);
        $eventId = (string) ($event['id'] ?? '');
        $type    = (string) ($event['event'] ?? '');

        InboundWebhookEvent::updateOrCreate(
            ['provider' => 'mailgun', 'event_id' => $eventId !== '' ? $eventId : null],
            [
                'event_type'      => $type,
                'payload'         => $request->all(),
                'signature_valid' => true,
                'processed_at'    => now(),
            ]
        );

        $providerId = (string) ($event['message']['headers']['message-id'] ?? '');
        if ($providerId !== '') {
            $msg = OutreachMessage::query()
                ->withoutGlobalScopes()
                ->where('provider_message_id', $providerId)
                ->first();
            if ($msg) {
                $events = $msg->provider_events ?? [];
                $events[] = ['type' => $type, 'at' => now()->toIso8601String()];
                $update = ['provider_events' => $events];

                match ($type) {
                    'delivered' => $update['status']     = 'delivered',
                    'opened'    => $update['status']     = 'opened',
                    'clicked'   => $update['status']     = 'clicked',
                    'failed', 'permanent_fail' => $update['status'] = 'bounced',
                    'replied'   => [$update['status']    = 'replied', $update['replied_at'] = now()],
                    default     => null,
                };
                $msg->forceFill($update)->saveQuietly();
            }
        }

        return response()->json(['ok' => true]);
    }
}
