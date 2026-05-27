<?php

namespace App\Services\Scheduling\Providers;

use App\Models\CalendarConnection;
use App\Models\InterviewSlot;
use App\Services\Scheduling\CalendarProvider;
use Illuminate\Support\Str;

/**
 * Records a fake event ID and uses any meeting_url the slot already carries.
 * Useful for local dev + tests, and as the fallback when a tenant has not
 * yet connected a real calendar.
 */
class NoopCalendarProvider implements CalendarProvider
{
    public function name(): string { return 'noop'; }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $redirectUri . '?state=' . urlencode($state) . '&code=NOOP_' . Str::random(8);
    }

    public function exchangeCode(string $code, string $redirectUri, int $tenantId, ?int $userId = null): CalendarConnection
    {
        return CalendarConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => 'noop', 'account_email' => 'noop@local'],
            [
                'user_id'                 => $userId,
                'access_token'            => 'noop',
                'refresh_token'           => null,
                'access_token_expires_at' => now()->addYear(),
                'scopes'                  => ['noop'],
                'is_active'               => true,
            ],
        );
    }

    public function refresh(CalendarConnection $connection): void
    {
        $connection->update(['access_token_expires_at' => now()->addYear()]);
    }

    public function createEvent(CalendarConnection $connection, InterviewSlot $slot, array $attendeeEmails): array
    {
        return [
            'event_id'    => 'noop_' . $slot->public_id,
            'meeting_url' => $slot->meeting_url, // pass through if operator set one
        ];
    }

    public function cancelEvent(CalendarConnection $connection, string $providerEventId): void
    {
        // No-op
    }
}
