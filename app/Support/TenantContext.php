<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Process-scoped current tenant. Bound by:
 *  - VerifyApiKey middleware (machine API requests)
 *  - EnforceTenantScope middleware (UI requests, derived from authenticated user)
 *  - Queue jobs that explicitly bind() before dispatching domain work
 *
 * Never read .env or query string for tenant — those are spoofable.
 */
class TenantContext
{
    private static ?Tenant $tenant = null;

    public static function bind(Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function bindById(int $id): void
    {
        self::$tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($id);
    }

    public static function clear(): void
    {
        self::$tenant = null;
    }

    public static function id(): ?int
    {
        return self::$tenant?->id;
    }

    public static function current(): ?Tenant
    {
        return self::$tenant;
    }

    public static function require(): Tenant
    {
        if (! self::$tenant) {
            throw new \RuntimeException('No tenant bound in TenantContext.');
        }
        return self::$tenant;
    }
}
