<?php

namespace App\Services\Scheduling\Providers;

use App\Models\CalendarConnection;
use App\Models\InterviewSlot;
use App\Services\Scheduling\CalendarProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleCalendarProvider implements CalendarProvider
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE  = 'https://www.googleapis.com/calendar/v3';

    private array $scopes = [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    public function __construct(private ?Client $http = null) {}

    public function name(): string { return 'google'; }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $clientId = $this->clientId();
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->scopes),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri, int $tenantId, ?int $userId = null): CalendarConnection
    {
        $resp = $this->client()->post(self::TOKEN_URL, [
            'form_params' => [
                'code'          => $code,
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ],
        ]);
        $body = json_decode((string) $resp->getBody(), true) ?: [];

        $access  = (string) ($body['access_token']  ?? '');
        $refresh = $body['refresh_token'] ?? null;
        $expires = isset($body['expires_in']) ? now()->addSeconds((int) $body['expires_in']) : null;
        if ($access === '') throw new RuntimeException('Google did not return an access_token.');

        $email = $this->fetchAccountEmail($access);

        return CalendarConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => 'google', 'account_email' => $email],
            [
                'user_id'                 => $userId,
                'access_token'            => $access,
                'refresh_token'           => $refresh,
                'access_token_expires_at' => $expires,
                'scopes'                  => $this->scopes,
                'is_active'               => true,
            ],
        );
    }

    public function refresh(CalendarConnection $connection): void
    {
        if (! $connection->refresh_token) {
            throw new RuntimeException('Google connection has no refresh_token; reconnect required.');
        }

        $resp = $this->client()->post(self::TOKEN_URL, [
            'form_params' => [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $connection->refresh_token,
                'grant_type'    => 'refresh_token',
            ],
        ]);
        $body = json_decode((string) $resp->getBody(), true) ?: [];
        $access  = (string) ($body['access_token'] ?? '');
        $expires = isset($body['expires_in']) ? now()->addSeconds((int) $body['expires_in']) : null;
        if ($access === '') throw new RuntimeException('Google refresh did not return an access_token.');

        $connection->update([
            'access_token'            => $access,
            'access_token_expires_at' => $expires,
        ]);
    }

    public function createEvent(CalendarConnection $connection, InterviewSlot $slot, array $attendeeEmails): array
    {
        $this->ensureFresh($connection);

        $calendarId = $connection->calendar_id ?: 'primary';
        $tz         = $slot->tenant->timezone ?: ($connection->timezone ?: 'UTC');

        $body = [
            'summary'     => "Interview: {$slot->candidate->name} for {$slot->jobPosting->title}",
            'description' => $slot->notes,
            'start'       => ['dateTime' => $slot->starts_at->toIso8601String(), 'timeZone' => $tz],
            'end'         => ['dateTime' => $slot->ends_at->toIso8601String(),   'timeZone' => $tz],
            'attendees'   => array_map(fn ($e) => ['email' => $e], array_filter($attendeeEmails)),
            'conferenceData' => [
                'createRequest' => [
                    'requestId'         => 'eiaaw-' . $slot->public_id,
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];

        $resp = $this->client()->post(self::API_BASE . "/calendars/{$calendarId}/events?conferenceDataVersion=1", [
            'headers' => ['Authorization' => 'Bearer ' . $connection->access_token, 'Content-Type' => 'application/json'],
            'json'    => $body,
        ]);
        $event = json_decode((string) $resp->getBody(), true) ?: [];

        return [
            'event_id'    => (string) ($event['id'] ?? ''),
            'meeting_url' => $event['hangoutLink'] ?? $event['conferenceData']['entryPoints'][0]['uri'] ?? null,
        ];
    }

    public function cancelEvent(CalendarConnection $connection, string $providerEventId): void
    {
        $this->ensureFresh($connection);
        $calendarId = $connection->calendar_id ?: 'primary';

        try {
            $this->client()->delete(self::API_BASE . "/calendars/{$calendarId}/events/" . rawurlencode($providerEventId), [
                'headers' => ['Authorization' => 'Bearer ' . $connection->access_token],
            ]);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarProvider: cancel failed', [
                'event_id' => $providerEventId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function ensureFresh(CalendarConnection $connection): void
    {
        if ($connection->isExpired()) $this->refresh($connection);
    }

    private function fetchAccountEmail(string $accessToken): string
    {
        $resp = $this->client()->get('https://openidconnect.googleapis.com/v1/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);
        $body = json_decode((string) $resp->getBody(), true) ?: [];
        return (string) ($body['email'] ?? 'unknown@google');
    }

    private function client(): Client
    {
        return $this->http ?? new Client(['http_errors' => true, 'timeout' => 30]);
    }

    private function clientId(): string
    {
        return (string) (config('services.google.client_id') ?? throw new RuntimeException('GOOGLE_CLIENT_ID not configured.'));
    }

    private function clientSecret(): string
    {
        return (string) (config('services.google.client_secret') ?? throw new RuntimeException('GOOGLE_CLIENT_SECRET not configured.'));
    }
}
