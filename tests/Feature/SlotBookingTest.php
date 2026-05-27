<?php

use App\Models\Candidate;
use App\Models\InterviewSlot;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Services\Scheduling\SlotBookingService;
use App\Support\TenantContext;

it('confirms a proposed slot via the noop provider and advances candidate stage', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'X', 'slug' => 'x', 'contact_email' => 'x@x.test']);
    TenantContext::bind($tenant);

    $job = JobPosting::create(['public_id' => (string) \Str::uuid(), 'title' => 'Eng', 'status' => 'sourcing']);
    $candidate = Candidate::create([
        'public_id' => (string) \Str::uuid(),
        'job_posting_id' => $job->id,
        'name' => 'Alice',
        'email' => 'alice@x.test',
        'stage' => 'replied',
        'lead_temperature' => 'Cold',
        'confidence_score' => 'High',
        'candidate_type' => 'B2C',
    ]);
    $slot = InterviewSlot::create([
        'public_id' => (string) \Str::uuid(),
        'candidate_id' => $candidate->id,
        'job_posting_id' => $job->id,
        'starts_at' => now()->addDays(2)->setHour(10),
        'ends_at'   => now()->addDays(2)->setHour(10)->addMinutes(30),
        'status'    => 'proposed',
        'meeting_url' => 'https://meet.example/abc',
    ]);

    app(SlotBookingService::class)->confirm($slot);

    $slot->refresh();
    $candidate->refresh();

    expect($slot->status)->toBe('confirmed')
        ->and($slot->meeting_url)->toBe('https://meet.example/abc')
        ->and($candidate->stage)->toBe('interview_scheduled')
        ->and($slot->notes)->toContain('event_id=noop_');
});
