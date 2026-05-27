<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\HandleInboundReplyJob;
use App\Models\InboundWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mailgun inbound route receives the FULL reply payload (different shape from
 * /webhooks/mailgun event notifications). Configure a Mailgun route like:
 *   match_recipient(".*@notify.eiaawsolutions.com") -> store(notify), forward(/webhooks/mailgun-inbound)
 *
 * The route POSTs multipart/form-data with: sender, recipient, From, To,
 * subject, body-plain, body-html, In-Reply-To, References, Message-Id,
 * stripped-text (the reply minus the quoted body), and signature/timestamp/token.
 */
class MailgunInboundController extends Controller
{
    public function inbound(Request $request): JsonResponse
    {
        $payload = $request->all();
        $messageId = (string) ($payload['Message-Id'] ?? $payload['message-id'] ?? '');

        $event = InboundWebhookEvent::updateOrCreate(
            ['provider' => 'mailgun', 'event_id' => $messageId !== '' ? "inbound:{$messageId}" : null],
            [
                'event_type'      => 'inbound_reply',
                'payload'         => $payload,
                'signature_valid' => true, // signature already verified by middleware
            ]
        );

        HandleInboundReplyJob::dispatch($event->id);

        return response()->json(['ok' => true]);
    }
}
