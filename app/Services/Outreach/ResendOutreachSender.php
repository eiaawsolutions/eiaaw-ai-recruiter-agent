<?php

namespace App\Services\Outreach;

use App\Models\OutreachMessage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends outreach via Resend (https://resend.com).
 *
 * Resend handles RFC822 details for us. We add a custom `X-EIAAW-Outreach-Id`
 * header so inbound replies can be matched back to the outreach row in
 * HandleInboundReplyJob.
 *
 * Per the EIAAW Lead Generation Contract we never enable open- or
 * click-tracking. Resend does not inject tracking pixels by default — we
 * keep it that way and never send `tags` that the user might confuse with
 * tracking.
 */
class ResendOutreachSender
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(private ?Client $http = null) {}

    public function send(OutreachMessage $msg): void
    {
        $apiKey = (string) config('services.resend.key');
        if ($apiKey === '' || str_starts_with($apiKey, 'secret://')) {
            throw new RuntimeException('Resend API key not configured (RESEND_API).');
        }
        if (! $msg->to_address) {
            $msg->update(['status' => 'failed']);
            throw new RuntimeException('Outreach has no to_address.');
        }

        $http = $this->http ?? new Client([
            'http_errors' => true,
            'timeout'     => 25,
        ]);

        try {
            $resp = $http->post(self::ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'from'    => $msg->from_address ?: config('mail.from.address'),
                    'to'      => [$msg->to_address],
                    'subject' => $msg->subject,
                    'text'    => $msg->body,
                    'reply_to' => $msg->reply_to ?: ($msg->from_address ?: config('mail.from.address')),
                    'headers' => [
                        'X-EIAAW-Outreach-Id' => $msg->public_id,
                    ],
                ],
            ]);

            $body  = json_decode((string) $resp->getBody(), true) ?: [];
            $msgId = (string) ($body['id'] ?? '');

            $msg->update([
                'status'              => 'sent',
                'sent_at'             => now(),
                'provider'            => 'resend',
                'provider_message_id' => $msgId,
            ]);
        } catch (\Throwable $e) {
            Log::error('ResendOutreachSender: send failed', [
                'outreach_id' => $msg->public_id,
                'error'       => $e->getMessage(),
            ]);
            $msg->update(['status' => 'failed']);
            throw $e;
        }
    }
}
