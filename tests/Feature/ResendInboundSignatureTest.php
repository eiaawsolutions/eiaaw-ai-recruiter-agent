<?php

use App\Models\Candidate;
use App\Models\InboundWebhookEvent;
use App\Models\JobPosting;
use App\Models\OutreachMessage;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('rejects an unsigned request to the Resend webhook', function () {
    $this->postJson('/webhooks/resend', ['type' => 'email.delivered', 'data' => ['email_id' => 'x']])
        ->assertStatus(401)
        ->assertJsonPath('error', 'missing_signature');
});

it('accepts a properly signed payload and updates outreach status on email.delivered', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'T', 'slug' => 't', 'contact_email' => 't@x.test']);
    TenantContext::bind($tenant);
    $job = JobPosting::create(['public_id' => (string) \Str::uuid(), 'title' => 'Eng', 'status' => 'sourcing']);
    $candidate = Candidate::create([
        'public_id' => (string) \Str::uuid(), 'job_posting_id' => $job->id, 'name' => 'Bob',
        'email' => 'bob@x.test', 'stage' => 'outreach_sent',
        'lead_temperature' => 'Cold', 'confidence_score' => 'High', 'candidate_type' => 'B2C',
    ]);
    $msg = OutreachMessage::create([
        'public_id' => (string) \Str::uuid(), 'candidate_id' => $candidate->id, 'job_posting_id' => $job->id,
        'channel' => 'email', 'subject' => 'X', 'body' => 'b',
        'to_address' => 'bob@x.test', 'status' => 'sent',
        'provider_message_id' => 'resend-msg-zzz',
    ]);
    TenantContext::clear();

    // Build a valid Svix signature
    $svixId = 'msg_test_1';
    $svixTs = (string) time();
    $payload = json_encode([
        'type' => 'email.delivered',
        'data' => ['email_id' => 'resend-msg-zzz'],
    ]);
    // Test signing secret from phpunit.xml = whsec_dGVzdF9zZWNyZXRfMTIzNDU2Nzg5MA==
    $rawKey = base64_decode('dGVzdF9zZWNyZXRfMTIzNDU2Nzg5MA==');
    $sig = base64_encode(hash_hmac('sha256', "{$svixId}.{$svixTs}.{$payload}", $rawKey, true));

    $this->call(
        'POST',
        '/webhooks/resend',
        [], [], [],
        [
            'HTTP_SVIX_ID' => $svixId,
            'HTTP_SVIX_TIMESTAMP' => $svixTs,
            'HTTP_SVIX_SIGNATURE' => "v1,{$sig}",
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertOk();

    $msg->refresh();
    expect($msg->status)->toBe('delivered');

    $event = InboundWebhookEvent::query()->where('provider', 'resend')->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('email.delivered')
        ->and($event->signature_valid)->toBeTrue();
});
