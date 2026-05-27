<?php

namespace App\Services\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;

class WebhookDispatcher
{
    public function dispatch(string $eventType, array $payload): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) return;

        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => $e->subscribesTo($eventType));

        $envelope = [
            'event' => $eventType,
            'sent_at' => now()->toIso8601String(),
            'tenant_id' => TenantContext::current()->public_id,
            'data' => $payload,
        ];
        $body = json_encode($envelope, JSON_UNESCAPED_SLASHES);

        foreach ($endpoints as $endpoint) {
            $signature = 'sha256=' . hash_hmac('sha256', $body, $endpoint->secret);

            $delivery = WebhookDelivery::create([
                'tenant_id'           => $tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_type'          => $eventType,
                'payload'             => $envelope,
                'signature'           => $signature,
                'status'              => 'queued',
                'attempts'            => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }
    }
}
