<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Brand\BrandDnaExtractor;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractBrandDnaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(public int $tenantId, public string $url) {}

    public function handle(BrandDnaExtractor $extractor): void
    {
        TenantContext::bindById($this->tenantId);
        try {
            $tenant = Tenant::query()->findOrFail($this->tenantId);
            $extractor->extract($tenant, $this->url);
        } catch (\Throwable $e) {
            Log::warning('ExtractBrandDnaJob: failed', [
                'tenant_id' => $this->tenantId,
                'url'       => $this->url,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            TenantContext::clear();
        }
    }
}
