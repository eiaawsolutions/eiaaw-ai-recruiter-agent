<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\TenantContext;
use App\Support\UrlGuard;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;     // we manage retries manually with exponential backoff
    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->findOrFail($this->deliveryId);
        TenantContext::bindById($delivery->tenant_id);

        $endpoint = $delivery->endpoint;
        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'abandoned', 'last_error' => 'endpoint_inactive']);
            TenantContext::clear();
            return;
        }

        $maxAttempts = (int) config('services.recruiter.webhook_retry_max', 8);
        $header      = (string) config('services.recruiter.webhook_signature_header', 'X-EIAAW-Signature');

        // Re-validate the destination URL at delivery time — the row was checked
        // at creation, but DNS/host can drift, and we never want the worker
        // pivoting to a private address.
        if (! UrlGuard::isSafe($endpoint->url)) {
            $delivery->update([
                'status'     => 'abandoned',
                'last_error' => 'endpoint_url_unsafe',
            ]);
            $endpoint->forceFill([
                'is_active'       => false,
                'last_failure_at' => now(),
            ])->saveQuietly();
            TenantContext::clear();
            return;
        }

        try {
            $http = new Client([
                'http_errors' => false,
                'timeout'     => 25,
                'verify'      => true,
                // Re-check every redirect target to defeat redirect-to-internal.
                'allow_redirects' => [
                    'max'         => 3,
                    'strict'      => true,
                    'referer'     => false,
                    'protocols'   => ['https'],
                    'on_redirect' => function ($req, $resp, $uri) {
                        UrlGuard::assertSafe((string) $uri);
                    },
                ],
            ]);
            $response = $http->post($endpoint->url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent'   => 'EIAAW-Recruiter-Webhook/1.0',
                    $header        => $delivery->signature,
                    'X-EIAAW-Event' => $delivery->event_type,
                    'X-EIAAW-Delivery' => $delivery->public_id,
                ],
                'body' => json_encode($delivery->payload, JSON_UNESCAPED_SLASHES),
            ]);

            $status = $response->getStatusCode();
            $delivery->attempts = $delivery->attempts + 1;
            $delivery->http_status = $status;

            if ($status >= 200 && $status < 300) {
                $delivery->status       = 'delivered';
                $delivery->delivered_at = now();
                $delivery->last_error   = null;
                $endpoint->forceFill([
                    'last_success_at'      => now(),
                    'consecutive_failures' => 0,
                ])->saveQuietly();
            } else {
                $this->scheduleRetry($delivery, $maxAttempts, "non2xx_{$status}");
                $endpoint->forceFill([
                    'last_failure_at'      => now(),
                    'consecutive_failures' => $endpoint->consecutive_failures + 1,
                ])->saveQuietly();
            }
            $delivery->save();
        } catch (\Throwable $e) {
            Log::warning('DeliverWebhookJob: error', ['delivery_id' => $delivery->id, 'error' => $e->getMessage()]);
            $delivery->attempts++;
            $delivery->last_error = mb_substr($e->getMessage(), 0, 1000);
            $this->scheduleRetry($delivery, $maxAttempts, 'exception');
            $delivery->save();
        } finally {
            TenantContext::clear();
        }
    }

    private function scheduleRetry(WebhookDelivery $d, int $maxAttempts, string $reason): void
    {
        if ($d->attempts >= $maxAttempts) {
            $d->status = 'abandoned';
            $d->last_error = $d->last_error ?: $reason;
            return;
        }
        $d->status = 'queued';
        $delaySeconds = (int) min(3600, pow(2, $d->attempts) * 15); // 15,30,60,120,...,3600
        $d->next_retry_at = now()->addSeconds($delaySeconds);
        $d->last_error = $d->last_error ?: $reason;

        self::dispatch($d->id)->delay(now()->addSeconds($delaySeconds));
    }
}
