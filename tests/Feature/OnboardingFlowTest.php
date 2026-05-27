<?php

use App\Models\Tenant;
use App\Models\User;

it('creates a tenant + owner user via the public onboarding form', function () {
    \Illuminate\Support\Facades\Bus::fake();

    $resp = $this->post(route('onboarding.store'), [
        'tenant_name'    => 'Acme Co',
        'contact_email'  => 'owner@acme.test',
        'admin_name'     => 'Owner One',
        'admin_password' => 'super-secret-pw',
        'timezone'       => 'Asia/Kuala_Lumpur',
    ]);

    $resp->assertRedirect(route('onboarding.brand'));

    $tenant = Tenant::query()->where('slug', 'acme-co')->firstOrFail();
    $user   = User::query()->withoutGlobalScopes()->where('email', 'owner@acme.test')->firstOrFail();

    expect($tenant->contact_email)->toBe('owner@acme.test')
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->role)->toBe('owner')
        ->and(auth()->id())->toBe($user->id);
});

it('queues the brand-DNA extraction job when a brand_url is provided', function () {
    \Illuminate\Support\Facades\Bus::fake();

    $this->post(route('onboarding.store'), [
        'tenant_name'    => 'Beta Inc',
        'contact_email'  => 'owner@beta.test',
        'admin_name'     => 'Owner Two',
        'admin_password' => 'super-secret-pw',
        'brand_url'      => 'https://beta.example/about',
    ])->assertRedirect();

    \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\ExtractBrandDnaJob::class);
});

it('rejects duplicate tenant slugs', function () {
    Tenant::create([
        'public_id' => (string) \Str::uuid(),
        'name'  => 'Acme Co',
        'slug'  => 'acme-co',
        'contact_email' => 'other@acme.test',
    ]);

    $this->post(route('onboarding.store'), [
        'tenant_name'    => 'Acme Co',
        'contact_email'  => 'owner@acme.test',
        'admin_name'     => 'Owner',
        'admin_password' => 'super-secret-pw',
    ])->assertSessionHasErrors('tenant_name');
});
