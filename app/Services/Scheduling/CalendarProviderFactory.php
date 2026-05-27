<?php

namespace App\Services\Scheduling;

use App\Services\Scheduling\Providers\GoogleCalendarProvider;
use App\Services\Scheduling\Providers\MicrosoftCalendarProvider;
use App\Services\Scheduling\Providers\NoopCalendarProvider;
use InvalidArgumentException;

class CalendarProviderFactory
{
    public function make(string $name): CalendarProvider
    {
        return match (strtolower($name)) {
            'google'    => app(GoogleCalendarProvider::class),
            'microsoft' => app(MicrosoftCalendarProvider::class),
            'noop'      => app(NoopCalendarProvider::class),
            default     => throw new InvalidArgumentException("Unknown calendar provider: {$name}"),
        };
    }
}
