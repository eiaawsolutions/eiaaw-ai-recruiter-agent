<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\CandidateController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\JobController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\OutreachController;
use App\Http\Controllers\Webhooks\MailgunInboundController;
use App\Http\Controllers\Webhooks\MailgunWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing')->name('home');

// Public onboarding (creates a tenant, then logs the user in)
Route::middleware('guest')->group(function () {
    Route::get('/get-started',  [OnboardingController::class, 'start'])->name('onboarding.start');
    Route::post('/get-started', [OnboardingController::class, 'storeStart'])->name('onboarding.store');

    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'tenant.scope'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Onboarding steps 2 & 3 (authenticated)
    Route::get('/onboarding/brand',          [OnboardingController::class, 'brand'])->name('onboarding.brand');
    Route::post('/onboarding/brand/rerun',   [OnboardingController::class, 'rerunBrand'])->name('onboarding.rerun_brand');
    Route::get('/onboarding/keys',           [OnboardingController::class, 'keys'])->name('onboarding.keys');
    Route::post('/onboarding/keys/mint',     [OnboardingController::class, 'mintKey'])->name('onboarding.mint_key');
    Route::post('/onboarding/webhooks',      [OnboardingController::class, 'registerWebhook'])->name('onboarding.register_webhook');
    Route::post('/onboarding/finish',        [OnboardingController::class, 'finish'])->name('onboarding.finish');

    Route::get('/dashboard',   [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/jobs',                 [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/new',             [JobController::class, 'create'])->name('jobs.create');
    Route::post('/jobs',                [JobController::class, 'store'])->name('jobs.store');
    Route::get('/jobs/{publicId}',      [JobController::class, 'show'])->name('jobs.show');
    Route::post('/jobs/{publicId}/source', [JobController::class, 'source'])->name('jobs.source');

    Route::get('/candidates',                       [CandidateController::class, 'index'])->name('candidates.index');
    Route::get('/candidates/{publicId}',            [CandidateController::class, 'show'])->name('candidates.show');
    Route::post('/candidates/{publicId}/screen',    [CandidateController::class, 'screen'])->name('candidates.screen');
    Route::post('/candidates/{publicId}/draft',     [CandidateController::class, 'draft'])->name('candidates.draft');
    Route::post('/candidates/{publicId}/shortlist', [CandidateController::class, 'shortlist'])->name('candidates.shortlist');
    Route::post('/candidates/{publicId}/discard',   [CandidateController::class, 'discard'])->name('candidates.discard');

    Route::get('/outreach',                       [OutreachController::class, 'index'])->name('outreach.index');
    Route::get('/outreach/{publicId}',            [OutreachController::class, 'show'])->name('outreach.show');
    Route::post('/outreach/{publicId}/approve',   [OutreachController::class, 'approve'])->name('outreach.approve');
    Route::post('/outreach/{publicId}/reject',    [OutreachController::class, 'reject'])->name('outreach.reject');

    // Calendar connections + slot confirmation
    Route::get('/calendars',                              [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendars/connect/{provider}',           [CalendarController::class, 'start'])->name('calendar.start');
    Route::get('/calendars/callback/{provider}',          [CalendarController::class, 'callback'])->name('calendar.callback');
    Route::post('/calendars/{id}/disconnect',             [CalendarController::class, 'disconnect'])->name('calendar.disconnect');
    Route::post('/interviews/{publicId}/confirm',         [CalendarController::class, 'confirmSlot'])->name('interviews.confirm');
});

// Mailgun event notifications: signature-verified, no auth.
Route::post('/webhooks/mailgun', [MailgunWebhookController::class, 'events'])
    ->middleware('mailgun.signature')
    ->name('webhooks.mailgun');

// Mailgun inbound route (full reply payload). Note: Mailgun's inbound route
// signs the same way as event webhooks; the same middleware applies.
Route::post('/webhooks/mailgun-inbound', [MailgunInboundController::class, 'inbound'])
    ->middleware('mailgun.signature')
    ->name('webhooks.mailgun-inbound');
