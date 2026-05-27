<?php

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Support\TenantContext;

beforeEach(function () {
    TenantContext::clear();
});

it('rejects requests without a key', function () {
    $this->getJson('/api/v1/jobs')
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'Unauthenticated');
});

it('accepts a valid bearer key and scopes to the right tenant', function () {
    $tenantA = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'A', 'slug' => 'a', 'contact_email' => 'a@x.test']);
    $tenantB = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'B', 'slug' => 'b', 'contact_email' => 'b@x.test']);

    [, $plaintextA] = ApiKey::mint($tenantA, 'A key', ['*']);
    [, $plaintextB] = ApiKey::mint($tenantB, 'B key', ['*']);

    $this->withHeaders(['Authorization' => "Bearer {$plaintextA}"])
        ->postJson('/api/v1/jobs', ['title' => 'A role'])
        ->assertStatus(201);

    // Second tenant cannot see the first tenant's row
    $list = $this->withHeaders(['Authorization' => "Bearer {$plaintextB}"])
        ->getJson('/api/v1/jobs')
        ->assertOk()
        ->json('data');

    expect($list)->toBeArray()->and($list)->toHaveCount(0);
});

it('rejects revoked keys', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'X', 'slug' => 'x', 'contact_email' => 'x@x.test']);
    [$key, $plaintext] = ApiKey::mint($tenant, 'X key', ['*']);
    $key->forceFill(['revoked_at' => now()])->save();

    $this->withHeaders(['Authorization' => "Bearer {$plaintext}"])
        ->getJson('/api/v1/jobs')
        ->assertStatus(401);
});
