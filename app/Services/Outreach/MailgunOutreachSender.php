<?php

namespace App\Services\Outreach;

use App\Models\OutreachMessage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MailgunOutreachSender
{
    public function __construct(private ?Client $http = null) {}

    public function send(OutreachMessage $msg): void
    {
        $domain   = (string) config('services.mailgun.domain');
        $secret   = (string) config('services.mailgun.secret');
        $endpoint = (string) config('services.mailgun.endpoint', 'api.mailgun.net');

        if ($domain === '' || $secret === '' || str_starts_with($secret, 'secret://')) {
            throw new RuntimeException('Mailgun credentials not configured.');
        }
        if (! $msg->to_address) {
            $msg->update(['status' => 'failed']);
            throw new RuntimeException('Outreach has no to_address.');
        }

        $http = $this->http ?? new Client([
            'base_uri'    => "https://{$endpoint}",
            'http_errors' => true,
            'timeout'     => 25,
        ]);

        try {
            $resp = $http->post("/v3/{$domain}/messages", [
                'auth' => ['api', $secret],
                'form_params' => [
                    'from'    => $msg->from_address ?: config('mail.from.address'),
                    'to'      => $msg->to_address,
                    'subject' => $msg->subject,
                    'text'    => $msg->body,
                    'h:Reply-To' => $msg->reply_to ?: ($msg->from_address ?: config('mail.from.address')),
                    'h:X-EIAAW-Outreach-Id' => $msg->public_id,
                    'o:tracking-clicks' => 'no',  // Lead Generation Contract: no covert tracking
                    'o:tracking-opens'  => 'no',
                ],
            ]);
            $body = json_decode((string) $resp->getBody(), true) ?: [];
            $msgId = (string) ($body['id'] ?? '');
            $msg->update([
                'status'              => 'sent',
                'sent_at'             => now(),
                'provider_message_id' => $msgId,
            ]);
        } catch (\Throwable $e) {
            Log::error('MailgunOutreachSender: send failed', [
                'outreach_id' => $msg->public_id,
                'error'       => $e->getMessage(),
            ]);
            $msg->update(['status' => 'failed']);
            throw $e;
        }
    }
}
