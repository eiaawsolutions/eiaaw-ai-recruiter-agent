<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-scope to current tenant whenever one is bound.
        static::addGlobalScope('tenant', function (Builder $b) {
            if ($tenantId = TenantContext::id()) {
                $b->where($b->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        // Auto-stamp tenant_id on create. Refuse if no tenant is bound and the
        // model didn't set one explicitly — never silently leak across tenants.
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = TenantContext::id();
                if (! $tenantId) {
                    throw new \RuntimeException(sprintf(
                        '%s requires a tenant_id. Bind TenantContext or set tenant_id explicitly.',
                        class_basename($model),
                    ));
                }
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
