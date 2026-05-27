<?php

use App\Jobs\DeliverWebhookJob;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\TenantContext;

it('signs outbound payloads with HMAC-SHA256 over the JSON body', function () {
    \Illuminate\Support\Facades\Bus::fake();

    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'T', 'slug' => 't', 'contact_email' => 't@x.test']);
    TenantContext::bind($tenant);

    $endpoint = $tenant->webhookEndpoints()->create([
        'url'       => 'https://example.test/hook',
        'secret'    => 'whs_test_secret',
        'events'    => ['candidate.sourced'],
        'is_active' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch('candidate.sourced', ['candidate_id' => 'abc']);

    $delivery = WebhookDelivery::query()->first();
    expect($delivery)->not->toBeNull();

    $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
    $expected = 'sha256=' . hash_hmac('sha256', $body, 'whs_test_secret');

    expect($delivery->signature)->toBe($expected)
        ->and($delivery->event_type)->toBe('candidate.sourced');

    \Illuminate\Support\Facades\Bus::assertDispatched(DeliverWebhookJob::class);
});
