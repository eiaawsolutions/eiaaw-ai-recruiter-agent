<?php

namespace App\Services\Scheduling;

use App\Models\CalendarConnection;
use App\Models\InterviewSlot;

interface CalendarProvider
{
    public function name(): string;

    /**
     * Begin the OAuth authorization flow. Returns the URL the operator must
     * visit; the callback handler exchanges the code for tokens.
     */
    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * Exchange the OAuth code for tokens and create/update the connection row.
     */
    public function exchangeCode(string $code, string $redirectUri, int $tenantId, ?int $userId = null): CalendarConnection;

    /**
     * Refresh an expired access token in-place.
     */
    public function refresh(CalendarConnection $connection): void;

    /**
     * Create a calendar event for a confirmed interview slot. Returns the
     * provider event id + meeting URL (when the provider supplies one).
     *
     * @return array{event_id:string, meeting_url:?string}
     */
    public function createEvent(CalendarConnection $connection, InterviewSlot $slot, array $attendeeEmails): array;

    /**
     * Cancel a previously-created event.
     */
    public function cancelEvent(CalendarConnection $connection, string $providerEventId): void;
}
