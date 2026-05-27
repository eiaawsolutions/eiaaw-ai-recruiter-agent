<?php

namespace App\Services\Scheduling\Providers;

use App\Models\CalendarConnection;
use App\Models\InterviewSlot;
use App\Services\Scheduling\CalendarProvider;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MicrosoftCalendarProvider implements CalendarProvider
{
    private const TENANT      = 'common';
    private const AUTH_URL    = 'https://login.microsoftonline.com/' . self::TENANT . '/oauth2/v2.0/authorize';
    private const TOKEN_URL   = 'https://login.microsoftonline.com/' . self::TENANT . '/oauth2/v2.0/token';
    private const GRAPH_BASE  = 'https://graph.microsoft.com/v1.0';

    private array $scopes = [
        'offline_access',
        'openid',
        'email',
        'Calendars.ReadWrite',
        'OnlineMeetings.ReadWrite',
    ];

    public function __construct(private ?Client $http = null) {}

    public function name(): string { return 'microsoft'; }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri'  => $redirectUri,
            'response_mode' => 'query',
            'scope'         => implode(' ', $this->scopes),
            'state'         => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri, int $tenantId, ?int $userId = null): CalendarConnection
    {
        $resp = $this->client()->post(self::TOKEN_URL, [
            'form_params' => [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
                'scope'         => implode(' ', $this->scopes),
            ],
        ]);
        $body = json_decode((string) $resp->getBody(), true) ?: [];
        $access  = (string) ($body['access_token']  ?? '');
        $refresh = $body['refresh_token'] ?? null;
        $expires = isset($body['expires_in']) ? now()->addSeconds((int) $body['expires_in']) : null;
        if ($access === '') throw new RuntimeException('Microsoft did not return an access_token.');

        $email = $this->fetchAccountEmail($access);

        return CalendarConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => 'microsoft', 'account_email' => $email],
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
            throw new RuntimeException('Microsoft connection has no refresh_token; reconnect required.');
        }
        $resp = $this->client()->post(self::TOKEN_URL, [
            'form_params' => [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $connection->refresh_token,
                'grant_type'    => 'refresh_token',
                'scope'         => implode(' ', $this->scopes),
            ],
        ]);
        $body = json_decode((string) $resp->getBody(), true) ?: [];
        $access  = (string) ($body['access_token']  ?? '');
        $refresh = $body['refresh_token'] ?? $connection->refresh_token;
        $expires = isset($body['expires_in']) ? now()->addSeconds((int) $body['expires_in']) : null;
        if ($access === '') throw new RuntimeException('Microsoft refresh did not return an access_token.');

        $connection->update([
            'access_token'            => $access,
            'refresh_token'           => $refresh,
            'access_token_expires_at' => $expires,
        ]);
    }

    public function createEvent(CalendarConnection $connection, InterviewSlot $slot, array $attendeeEmails): array
    {
        $this->ensureFresh($connection);

        $tz = $slot->tenant->timezone ?: ($connection->timezone ?: 'UTC');
        $body = [
            'subject' => "Interview: {$slot->candidate->name} for {$slot->jobPosting->title}",
            'body'    => ['contentType' => 'text', 'content' => (string) $slot->notes],
            'start'   => ['dateTime' => $slot->starts_at->toIso8601String(), 'timeZone' => $tz],
            'end'     => ['dateTime' => $slot->ends_at->toIso8601String(),   'timeZone' => $tz],
            'attendees' => array_map(fn ($e) => [
                'emailAddress' => ['address' => $e],
                'type' => 'required',
            ], array_filter($attendeeEmails)),
            'isOnlineMeeting'       => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
        ];

        $resp = $this->client()->post(self::GRAPH_BASE . '/me/events', [
            'headers' => ['Authorization' => 'Bearer ' . $connection->access_token, 'Content-Type' => 'application/json'],
            'json'    => $body,
        ]);
        $event = json_decode((string) $resp->getBody(), true) ?: [];

        return [
            'event_id'    => (string) ($event['id'] ?? ''),
            'meeting_url' => $event['onlineMeeting']['joinUrl'] ?? null,
        ];
    }

    public function cancelEvent(CalendarConnection $connection, string $providerEventId): void
    {
        $this->ensureFresh($connection);
        try {
            $this->client()->delete(self::GRAPH_BASE . '/me/events/' . rawurlencode($providerEventId), [
                'headers' => ['Authorization' => 'Bearer ' . $connection->access_token],
            ]);
        } catch (\Throwable $e) {
            Log::warning('MicrosoftCalendarProvider: cancel failed', ['error' => $e->getMessage()]);
        }
    }

    private function ensureFresh(CalendarConnection $c): void
    {
        if ($c->isExpired()) $this->refresh($c);
    }

    private function fetchAccountEmail(string $accessToken): string
    {
        $resp = $this->client()->get(self::GRAPH_BASE . '/me', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);
        $body = json_decode((string) $resp->getBody(), true) ?: [];
        return (string) ($body['mail'] ?? $body['userPrincipalName'] ?? 'unknown@microsoft');
    }

    private function client(): Client
    {
        return $this->http ?? new Client(['http_errors' => true, 'timeout' => 30]);
    }

    private function clientId(): string
    {
        return (string) (config('services.microsoft.client_id') ?? throw new RuntimeException('MICROSOFT_CLIENT_ID not configured.'));
    }

    private function clientSecret(): string
    {
        return (string) (config('services.microsoft.client_secret') ?? throw new RuntimeException('MICROSOFT_CLIENT_SECRET not configured.'));
    }
}
