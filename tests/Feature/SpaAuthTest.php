<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // SPA endpoints sit behind CSRF in production; tests opt out so we don't
    // have to fetch the cookie first. Behaviour identical to a real SPA after
    // the CSRF-cookie call.
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

it('logs an SPA user in via cookie session and returns the user + tenant', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'T', 'slug' => 't', 'contact_email' => 't@x.test']);
    User::create([
        'tenant_id' => $tenant->id, 'name' => 'U', 'email' => 'u@x.test',
        'password' => Hash::make('hunter2hunter'), 'role' => 'owner',
    ]);

    $login = $this->postJson('/api/v1/spa/login', ['email' => 'u@x.test', 'password' => 'hunter2hunter']);
    $login->assertOk()->assertJsonPath('data.email', 'u@x.test')->assertJsonPath('data.tenant.slug', 't');

    $me = $this->getJson('/api/v1/spa/me');
    $me->assertOk()->assertJsonPath('data.email', 'u@x.test');
});

it('rejects bad SPA credentials with 422', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'T', 'slug' => 't', 'contact_email' => 't@x.test']);
    User::create([
        'tenant_id' => $tenant->id, 'name' => 'U', 'email' => 'u@x.test',
        'password' => Hash::make('hunter2hunter'), 'role' => 'owner',
    ]);

    $this->postJson('/api/v1/spa/login', ['email' => 'u@x.test', 'password' => 'wrong'])
        ->assertStatus(422);
});

it('mints a Sanctum personal token after SPA login', function () {
    $tenant = Tenant::create(['public_id' => (string) \Str::uuid(), 'name' => 'T', 'slug' => 't', 'contact_email' => 't@x.test']);
    User::create([
        'tenant_id' => $tenant->id, 'name' => 'U', 'email' => 'u@x.test',
        'password' => Hash::make('hunter2hunter'), 'role' => 'owner',
    ]);

    $this->postJson('/api/v1/spa/login', ['email' => 'u@x.test', 'password' => 'hunter2hunter']);

    $resp = $this->postJson('/api/v1/spa/tokens', ['name' => 'My CLI']);
    $resp->assertStatus(201)
        ->assertJsonStructure(['data' => ['name', 'token', 'abilities', 'created_at']]);
    expect($resp->json('data.token'))->toStartWith('1|'); // Sanctum plaintext format
});
