<?php

namespace App\Providers;

use App\Services\Secrets\InfisicalResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class SecretsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $infisicalConfig = config('secrets.infisical', []);

        $this->app->singleton(InfisicalResolver::class, function () use ($infisicalConfig) {
            return new InfisicalResolver($infisicalConfig);
        });

        if (! ($infisicalConfig['enabled'] ?? false)) {
            return;
        }

        if (empty($infisicalConfig['client_id']) || empty($infisicalConfig['client_secret']) || empty($infisicalConfig['project_id'])) {
            Log::debug('SecretsServiceProvider: skipped — Infisical creds incomplete.');
            return;
        }

        $resolver = $this->app->make(InfisicalResolver::class);
        $paths    = config('secrets.resolve', []);

        foreach ($paths as $path) {
            $current = config($path);
            if (! is_string($current) || ! str_starts_with($current, 'secret://')) {
                continue;
            }
            try {
                config([$path => $resolver->resolve($current)]);
            } catch (\Throwable $e) {
                Log::error('SecretsServiceProvider: resolution failed, leaving handle in place.', [
                    'config_path' => $path,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
