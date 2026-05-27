<?php

namespace App\Providers;

use App\Services\Agents\AnthropicClient;
use App\Services\Agents\DraftingAgent;
use App\Services\Agents\SchedulingAgent;
use App\Services\Agents\ScreeningAgent;
use App\Services\Agents\SourcingAgent;
use App\Services\Brand\BrandDnaExtractor;
use App\Services\Outreach\ReplyParser;
use App\Services\Scheduling\CalendarProviderFactory;
use App\Services\Scheduling\Providers\GoogleCalendarProvider;
use App\Services\Scheduling\Providers\MicrosoftCalendarProvider;
use App\Services\Scheduling\Providers\NoopCalendarProvider;
use App\Services\Scheduling\SlotBookingService;
use App\Services\Verification\LeadVerificationGate;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AnthropicClient::class);
        $this->app->singleton(LeadVerificationGate::class);

        $this->app->bind(SourcingAgent::class, function ($app) {
            return new SourcingAgent($app->make(AnthropicClient::class), $app->make(LeadVerificationGate::class));
        });
        $this->app->bind(ScreeningAgent::class);
        $this->app->bind(DraftingAgent::class);
        $this->app->bind(SchedulingAgent::class);

        // Brand DNA
        $this->app->bind(BrandDnaExtractor::class);

        // Calendar providers
        $this->app->singleton(GoogleCalendarProvider::class);
        $this->app->singleton(MicrosoftCalendarProvider::class);
        $this->app->singleton(NoopCalendarProvider::class);
        $this->app->singleton(CalendarProviderFactory::class);
        $this->app->bind(SlotBookingService::class);

        // Reply parsing
        $this->app->bind(ReplyParser::class);
    }
}
