<?php

use App\Models\ApiKey;
use App\Models\Tenant;

it('creates a job via the API and returns the canonical envelope', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'Acme', 'slug' => 'acme', 'contact_email' => 'a@acme.test']);
    [, $plaintext] = ApiKey::mint($tenant, 'k', ['*']);

    $resp = $this->withHeaders(['Authorization' => "Bearer {$plaintext}"])
        ->postJson('/api/v1/jobs', [
            'title' => 'Senior Backend Engineer',
            'seniority' => 'senior',
            'work_mode' => 'remote',
            'country' => 'MY',
            'must_haves' => ['5+ yrs Go', 'distributed systems'],
        ])
        ->assertStatus(201);

    expect($resp->json('data.title'))->toBe('Senior Backend Engineer')
        ->and($resp->json('data.must_haves'))->toHaveCount(2);
});

it('validates the country code length', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'Acme', 'slug' => 'acme', 'contact_email' => 'a@acme.test']);
    [, $plaintext] = ApiKey::mint($tenant, 'k', ['*']);

    $this->withHeaders(['Authorization' => "Bearer {$plaintext}"])
        ->postJson('/api/v1/jobs', ['title' => 'X', 'country' => 'MYS'])
        ->assertStatus(422);
});
