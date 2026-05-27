<?php

use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\OutreachMessage;
use App\Models\Tenant;
use App\Services\Outreach\ResendOutreachSender;
use App\Support\TenantContext;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('posts an outreach to Resend and stores the provider message id', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'T', 'slug' => 't', 'contact_email' => 't@x.test']);
    TenantContext::bind($tenant);

    $job = JobPosting::create(['public_id' => (string) \Str::uuid(), 'title' => 'Eng', 'status' => 'sourcing']);
    $candidate = Candidate::create([
        'public_id' => (string) \Str::uuid(), 'job_posting_id' => $job->id,
        'name' => 'Alice', 'email' => 'alice@x.test', 'stage' => 'screened',
        'lead_temperature' => 'Cold', 'confidence_score' => 'High', 'candidate_type' => 'B2C',
    ]);
    $msg = OutreachMessage::create([
        'public_id' => (string) \Str::uuid(), 'candidate_id' => $candidate->id, 'job_posting_id' => $job->id,
        'channel' => 'email', 'subject' => 'Hello', 'body' => 'body',
        'to_address' => 'alice@x.test', 'from_address' => 'recruiter@x.test',
        'status' => 'approved',
    ]);

    $captured = [];
    $mock = new MockHandler([
        new Response(200, [], json_encode(['id' => 'resend-msg-abc123'])),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(\GuzzleHttp\Middleware::tap(function (Request $r) use (&$captured) {
        $captured = [
            'method' => $r->getMethod(),
            'uri'    => (string) $r->getUri(),
            'auth'   => $r->getHeaderLine('Authorization'),
            'body'   => json_decode((string) $r->getBody(), true),
        ];
    }));
    $http = new Client(['handler' => $stack]);

    (new ResendOutreachSender($http))->send($msg);

    $msg->refresh();
    expect($msg->status)->toBe('sent')
        ->and($msg->provider)->toBe('resend')
        ->and($msg->provider_message_id)->toBe('resend-msg-abc123')
        ->and($captured['method'])->toBe('POST')
        ->and($captured['uri'])->toBe('https://api.resend.com/emails')
        ->and($captured['auth'])->toStartWith('Bearer ')
        ->and($captured['body']['headers']['X-EIAAW-Outreach-Id'])->toBe($msg->public_id);
});
