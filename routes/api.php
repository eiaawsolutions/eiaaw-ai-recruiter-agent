<?php

use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\HandoffController;
use App\Http\Controllers\Api\JobPostingController;
use App\Http\Controllers\Api\OutreachController;
use App\Http\Controllers\Api\SpaAuthController;
use App\Http\Controllers\Api\WebhookEndpointController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function ($request) {
    $limit = (int) config('services.recruiter.api_rate_limit_per_min', 120);
    $key = optional($request->attributes->get('api_key'))->id
        ?? $request->ip();
    return [\Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($key)];
});

Route::middleware(['api.key', 'throttle:api'])->prefix('v1')->group(function () {

    Route::get('/jobs',                     [JobPostingController::class, 'index']);
    Route::post('/jobs',                    [JobPostingController::class, 'store']);
    Route::get('/jobs/{publicId}',          [JobPostingController::class, 'show']);
    Route::delete('/jobs/{publicId}',       [JobPostingController::class, 'destroy']);
    Route::post('/jobs/{publicId}/source',  [JobPostingController::class, 'startSourcing']);

    Route::get('/candidates',                       [CandidateController::class, 'index']);
    Route::get('/candidates/{publicId}',            [CandidateController::class, 'show']);
    Route::post('/candidates/{publicId}/screen',    [CandidateController::class, 'screen']);
    Route::post('/candidates/{publicId}/draft',     [CandidateController::class, 'draftOutreach']);
    Route::post('/candidates/{publicId}/shortlist', [CandidateController::class, 'shortlist']);
    Route::post('/candidates/{publicId}/discard',   [CandidateController::class, 'discard']);

    Route::get('/outreach',                         [OutreachController::class, 'index']);
    Route::get('/outreach/{publicId}',              [OutreachController::class, 'show']);
    Route::post('/outreach/{publicId}/approve',     [OutreachController::class, 'approve']);
    Route::post('/outreach/{publicId}/reject',      [OutreachController::class, 'reject']);

    Route::get('/webhook-endpoints',                [WebhookEndpointController::class, 'index']);
    Route::post('/webhook-endpoints',               [WebhookEndpointController::class, 'store']);
    Route::delete('/webhook-endpoints/{id}',        [WebhookEndpointController::class, 'destroy']);

    Route::post('/handoff/workforce/{candidatePublicId}', [HandoffController::class, 'workforce']);
});

Route::get('/v1/health', fn () => response()->json(['ok' => true, 'service' => 'eiaaw-recruiter', 'time' => now()->toIso8601String()]));

// -------------------------------------------------------------------------
// SPA mode (Sanctum, cookie-session). For React/Vue/Svelte consoles.
// Set SANCTUM_STATEFUL_DOMAINS to the SPA origin and CORS_ALLOWED_ORIGINS
// to its URL.
// -------------------------------------------------------------------------
Route::prefix('v1/spa')->middleware('spa')->group(function () {
    Route::post('/login',  [SpaAuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/logout', [SpaAuthController::class, 'logout']);

    Route::middleware(['auth:sanctum', 'tenant.scope'])->group(function () {
        Route::get('/me',                  [SpaAuthController::class, 'me']);
        Route::get('/tokens',              [SpaAuthController::class, 'listTokens']);
        Route::post('/tokens',             [SpaAuthController::class, 'createToken']);
        Route::delete('/tokens/{id}',      [SpaAuthController::class, 'revokeToken']);
    });
});
