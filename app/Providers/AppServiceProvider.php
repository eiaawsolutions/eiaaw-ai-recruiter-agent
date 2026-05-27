<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(! $this->app->isProduction());

        // Rate limiter for API routes. Registered here (not in routes/api.php)
        // so it survives `route:cache` in production.
        RateLimiter::for('api', function ($request) {
            $limit = (int) config('services.recruiter.api_rate_limit_per_min', 120);
            $key = optional($request->attributes->get('api_key'))->id
                ?? $request->ip();
            return [Limit::perMinute($limit)->by($key)];
        });
    }
}
