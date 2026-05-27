<?php

use App\Services\Verification\LeadVerificationGate;

beforeEach(function () {
    $this->gate = new LeadVerificationGate([
        'min_verification_sources' => 1,
        'discard_low_confidence'   => true,
    ]);
});

it('accepts a verified high-quality candidate', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Alice Real',
        'reason_for_fit' => '10 yrs Go shipping payments at scale.',
        'linkedin_url' => 'https://www.linkedin.com/in/alice-real',
        'verification_sources' => [
            ['kind' => 'linkedin', 'url' => 'https://www.linkedin.com/in/alice-real'],
        ],
        'confidence_score' => 'High',
        'lead_temperature' => 'Cold',
    ]]);

    expect($outcome->accepted)->toHaveCount(1)
        ->and($outcome->rejected)->toHaveCount(0);
});

it('rejects a candidate with no verification sources', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Dan Unverified',
        'reason_for_fit' => 'Maybe a fit.',
        'verification_sources' => [],
        'confidence_score' => 'Medium',
    ]]);

    expect($outcome->rejected)->toHaveCount(1)
        ->and($outcome->rejected[0]['reasons'])->toContain('insufficient_verification_sources');
});

it('discards low-confidence rows', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Eve Lowconf',
        'reason_for_fit' => 'Tenuous fit.',
        'linkedin_url' => 'https://www.linkedin.com/in/eve',
        'verification_sources' => [['kind' => 'linkedin', 'url' => 'https://www.linkedin.com/in/eve']],
        'confidence_score' => 'Low',
    ]]);

    expect($outcome->rejected)->toHaveCount(1)
        ->and($outcome->rejected[0]['reasons'])->toContain('confidence_low_discarded');
});

it('strips a guessed email but keeps the candidate', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Frank Guessed',
        'reason_for_fit' => 'Strong on Go and Postgres internals.',
        'linkedin_url' => 'https://www.linkedin.com/in/frank',
        'verification_sources' => [['kind' => 'linkedin', 'url' => 'https://www.linkedin.com/in/frank', 'excerpt' => 'no email visible']],
        'email' => 'frank.guessed@somecompany.com',
        'confidence_score' => 'High',
    ]]);

    expect($outcome->accepted)->toHaveCount(1)
        ->and($outcome->accepted[0]['email'])->toBe('');
});

it('keeps an email that is present in a source excerpt', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Greta Public',
        'reason_for_fit' => 'Public-facing engineer; email on her about page.',
        'linkedin_url' => 'https://www.linkedin.com/in/greta',
        'verification_sources' => [[
            'kind' => 'company_site',
            'url' => 'https://greta.dev/about',
            'excerpt' => 'Contact: greta@greta.dev for collaborations.',
        ]],
        'email' => 'greta@greta.dev',
        'confidence_score' => 'High',
    ]]);

    expect($outcome->accepted)->toHaveCount(1)
        ->and($outcome->accepted[0]['email'])->toBe('greta@greta.dev');
});

it('requires a buying signal for Hot leads', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Hank Hot',
        'reason_for_fit' => 'Just posted he is looking for new roles.',
        'linkedin_url' => 'https://www.linkedin.com/in/hank',
        'verification_sources' => [['kind' => 'linkedin', 'url' => 'https://www.linkedin.com/in/hank']],
        'confidence_score' => 'High',
        'lead_temperature' => 'Hot',
        // missing buying_signal
    ]]);

    expect($outcome->rejected)->toHaveCount(1)
        ->and($outcome->rejected[0]['reasons'])->toContain('hot_lead_missing_buying_signal');
});

it('rejects a row missing a reason_for_fit', function () {
    $outcome = $this->gate->verifyBatch([[
        'name' => 'Ivy NoReason',
        'linkedin_url' => 'https://www.linkedin.com/in/ivy',
        'verification_sources' => [['kind' => 'linkedin', 'url' => 'https://www.linkedin.com/in/ivy']],
        'confidence_score' => 'High',
    ]]);

    expect($outcome->rejected)->toHaveCount(1)
        ->and($outcome->rejected[0]['reasons'])->toContain('missing_reason_for_fit');
});
