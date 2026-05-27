<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Log;

/**
 * LeadVerificationGate — enforces the EIAAW Lead Generation Contract.
 *
 * Every sourced candidate passes through this gate BEFORE persistence.
 * The contract is non-negotiable:
 *
 *  1. Never fabricate or infer contact data.
 *  2. Every lead MUST have a verifiable digital footprint (>=1 source URL).
 *  3. Emails / phones MUST NOT be guessed (no `first.last@company.com` patterns).
 *  4. Low-confidence rows are DISCARDED, not stored.
 *  5. Fewer high-quality leads > many unverified leads.
 *
 * Returns an Outcome containing accepted + rejected rows and a per-row reason
 * trace. Callers persist `accepted`, log `rejected`, and surface both to the
 * tenant for transparency.
 */
class LeadVerificationGate
{
    private array $disallowedEmailPatterns;
    private int   $minSources;
    private bool  $discardLowConfidence;

    public function __construct(?array $config = null)
    {
        $config = $config ?? config('services.recruiter', []);
        $this->minSources           = (int)  ($config['min_verification_sources'] ?? 1);
        $this->discardLowConfidence = (bool) ($config['discard_low_confidence'] ?? true);

        // Patterns that strongly indicate a guessed email rather than a
        // verified one. The agent prompt is instructed to leave email empty
        // when uncertain; this gate is a server-side belt-and-braces check.
        $this->disallowedEmailPatterns = [
            '/^[a-z]+\.[a-z]+@/i',                         // first.last@
            '/^[a-z]\.[a-z]+@/i',                          // f.last@
            '/^[a-z]+_[a-z]+@/i',                          // first_last@
            '/^[a-z]+\.?[a-z]?@(gmail|yahoo|hotmail|outlook|proton)/i', // generic personal addr w/o source
        ];
    }

    /**
     * Verify a batch of agent-produced candidate rows.
     *
     * @param  array<int, array<string,mixed>>  $rows
     * @return VerificationOutcome
     */
    public function verifyBatch(array $rows): VerificationOutcome
    {
        $accepted = [];
        $rejected = [];

        foreach ($rows as $row) {
            $result = $this->verifyOne($row);
            if ($result['ok']) {
                $accepted[] = $result['row'];
            } else {
                $rejected[] = [
                    'row'     => $row,
                    'reasons' => $result['reasons'],
                ];
            }
        }

        $this->logSummary($accepted, $rejected);

        return new VerificationOutcome($accepted, $rejected);
    }

    /**
     * Verify a single row. Returns:
     *   ['ok' => bool, 'row' => sanitized array, 'reasons' => string[]]
     */
    public function verifyOne(array $row): array
    {
        $reasons = [];
        $sanitized = $row;

        // 1. Required identity
        if (empty($row['name'])) {
            $reasons[] = 'missing_name';
        }

        // 2. Verification sources — at least N real URLs
        $sources = $this->normalizeSources($row['verification_sources'] ?? []);
        if (count($sources) < $this->minSources) {
            $reasons[] = 'insufficient_verification_sources';
        }
        $sanitized['verification_sources'] = $sources;

        // 3. Either linkedin_url or company_website MUST be a real http(s) URL
        $hasIdentityUrl = $this->isValidUrl($row['linkedin_url'] ?? null)
                       || $this->isValidUrl($row['company_website'] ?? null);
        if (! $hasIdentityUrl) {
            $reasons[] = 'no_identity_url';
        }

        // 4. Email — strip guessed patterns; never persist guesses.
        $email = trim((string) ($row['email'] ?? ''));
        if ($email !== '' && $this->looksGuessed($email, $row)) {
            $reasons[] = 'guessed_email_stripped';
            $email = '';
        }
        $sanitized['email'] = $email;

        // 5. Phone — only keep digits/+. If anything else, drop it.
        $phone = trim((string) ($row['phone'] ?? ''));
        if ($phone !== '' && ! preg_match('/^[+0-9 \-().]{6,40}$/', $phone)) {
            $reasons[] = 'invalid_phone_stripped';
            $phone = '';
        }
        $sanitized['phone'] = $phone;

        // 6. Confidence policy
        $confidence = (string) ($row['confidence_score'] ?? 'Low');
        if ($this->discardLowConfidence && strcasecmp($confidence, 'Low') === 0) {
            $reasons[] = 'confidence_low_discarded';
        }
        $sanitized['confidence_score'] = $this->normalizeConfidence($confidence);

        // 7. Hot leads must declare a buying signal
        $temperature = ucfirst(strtolower((string) ($row['lead_temperature'] ?? 'Cold')));
        if ($temperature === 'Hot' && empty($row['buying_signal'])) {
            $reasons[] = 'hot_lead_missing_buying_signal';
        }
        $sanitized['lead_temperature'] = $temperature;

        // 8. Reason for fit is required — agent must justify inclusion
        if (empty(trim((string) ($row['reason_for_fit'] ?? '')))) {
            $reasons[] = 'missing_reason_for_fit';
        }

        // Decide: any blocking reason => reject. Non-blocking reasons (e.g.
        // guessed_email_stripped) don't kill the row by themselves if the row
        // is otherwise complete.
        $blocking = array_intersect($reasons, [
            'missing_name',
            'insufficient_verification_sources',
            'no_identity_url',
            'confidence_low_discarded',
            'hot_lead_missing_buying_signal',
            'missing_reason_for_fit',
        ]);

        return [
            'ok'      => empty($blocking),
            'row'     => $sanitized,
            'reasons' => array_values($reasons),
        ];
    }

    private function normalizeSources(mixed $raw): array
    {
        if (! is_array($raw)) return [];
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                if ($this->isValidUrl($item)) {
                    $out[] = ['kind' => $this->guessKind($item), 'url' => $item];
                }
                continue;
            }
            if (is_array($item) && $this->isValidUrl($item['url'] ?? null)) {
                $out[] = [
                    'kind'    => $item['kind']    ?? $this->guessKind($item['url']),
                    'url'     => $item['url'],
                    'excerpt' => $item['excerpt'] ?? null,
                ];
            }
        }
        // Deduplicate by URL
        return array_values(collect($out)->keyBy('url')->all());
    }

    private function guessKind(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'linkedin.com'))                 return 'linkedin';
        if (str_contains($host, 'github.com'))                   return 'github';
        if (preg_match('/(twitter|x|threads|bsky|mastodon)/i', $host)) return 'social';
        if (str_contains($host, 'crunchbase.com'))               return 'directory';
        return 'other';
    }

    private function looksGuessed(string $email, array $row): bool
    {
        // If the email isn't present in any provided source excerpt, treat
        // pattern-matching addresses as guesses.
        $sourceText = '';
        foreach (($row['verification_sources'] ?? []) as $src) {
            if (is_array($src)) {
                $sourceText .= ' ' . ($src['excerpt'] ?? '');
            }
        }
        if (stripos($sourceText, $email) !== false) {
            return false; // explicitly observed
        }

        foreach ($this->disallowedEmailPatterns as $pat) {
            if (preg_match($pat, $email)) return true;
        }
        return false;
    }

    private function isValidUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '') return false;
        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('#^https?://#i', $url);
    }

    private function normalizeConfidence(string $c): string
    {
        $c = ucfirst(strtolower($c));
        return in_array($c, ['High', 'Medium', 'Low'], true) ? $c : 'Low';
    }

    private function logSummary(array $accepted, array $rejected): void
    {
        $reasonCounts = [];
        foreach ($rejected as $r) {
            foreach ($r['reasons'] as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }
        Log::info('LeadVerificationGate: batch verified', [
            'accepted'      => count($accepted),
            'rejected'      => count($rejected),
            'reason_counts' => $reasonCounts,
        ]);
    }
}
