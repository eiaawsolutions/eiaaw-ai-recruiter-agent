<?php

namespace App\Services\Integrations;

use App\Models\Candidate;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends a hired candidate row to EIAAW Workforce's OnboardingInvite flow.
 *
 * Workforce exposes an HMAC-signed POST endpoint expecting:
 *   { candidate_public_id, name, email, role_title, start_date?, comp? }
 *
 * If Workforce is not reachable, the row is preserved in the recruiter DB
 * and the operator can replay via the UI.
 */
class WorkforceHandoff
{
    public function __construct(private ?Client $http = null) {}

    public function send(Candidate $candidate, array $extras = []): array
    {
        $baseUrl = rtrim((string) config('services.workforce.base_url'), '/');
        $apiKey  = (string) config('services.workforce.api_key');
        $hmac    = (string) config('services.workforce.hmac_secret');

        if ($baseUrl === '' || $apiKey === '' || $hmac === '') {
            throw new RuntimeException('Workforce handoff is not configured.');
        }

        // Replay protection — every request carries a fresh nonce and a unix
        // timestamp. Both are inside the signed payload, so a captured request
        // cannot be replayed once the Workforce side rejects stale timestamps
        // (default 5-minute window) and seen nonces.
        $nonce     = (string) Str::uuid();
        $timestamp = (string) time();

        $payload = array_filter([
            'candidate_public_id' => $candidate->public_id,
            'name'                => $candidate->name,
            'email'               => $candidate->email,
            'role_title'          => $extras['role_title'] ?? $candidate->jobPosting->title,
            'start_date'          => $extras['start_date'] ?? null,
            'comp'                => $extras['comp']       ?? null,
            'source_recruiter'    => true,
            'nonce'               => $nonce,
            'timestamp'           => $timestamp,
        ], fn ($v) => $v !== null && $v !== '');

        // Use sorted-key JSON so the receiving side can re-serialize and verify
        // the signature deterministically.
        ksort($payload);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig  = 'sha256=' . hash_hmac('sha256', $body, $hmac);

        $http = $this->http ?? new Client([
            'http_errors' => false,
            'timeout'     => 30,
            'verify'      => true,
        ]);
        $resp = $http->post("{$baseUrl}/api/recruiter/handoff", [
            'headers' => [
                'Authorization'      => 'Bearer ' . $apiKey,
                'Content-Type'       => 'application/json',
                'X-EIAAW-Signature'  => $sig,
                'X-EIAAW-Timestamp'  => $timestamp,
                'X-EIAAW-Nonce'      => $nonce,
            ],
            'body' => $body,
        ]);

        $status = $resp->getStatusCode();
        $respBody = json_decode((string) $resp->getBody(), true) ?: [];

        if ($status < 200 || $status >= 300) {
            Log::warning('WorkforceHandoff: non-2xx', ['status' => $status, 'body' => $respBody]);
            throw new RuntimeException("Workforce handoff returned {$status}.");
        }

        return [
            'candidate_id' => $candidate->public_id,
            'status'       => $status,
            'workforce'    => $respBody,
        ];
    }
}
