<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * SSRF defense for any outbound HTTP destination we accept from tenants
 * (webhook URLs, brand-DNA crawl targets, etc.).
 *
 * Policy:
 *  - scheme MUST be https (http allowed only when APP_ENV=local AND host is localhost)
 *  - host MUST resolve to at least one address, and EVERY resolved address must
 *    be a public unicast address (no private/loopback/link-local/multicast/0.0.0.0)
 *  - default ports only (https:443, http:80 in local) — no arbitrary ports
 *  - no userinfo (foo:bar@example.com) — Guzzle would send the creds upstream
 *  - host MUST be a name or IPv4 — bracketed IPv6 literals rejected (rebind risk)
 *
 * The guard is intentionally strict. If a real customer needs a non-default
 * port or http target, that requires a deliberate config override per tenant,
 * not a runtime escape hatch.
 */
class UrlGuard
{
    /** RFC1918 + loopback + link-local + CGNAT + 0.0.0.0 + multicast/reserved. */
    private const PRIVATE_CIDRS = [
        '0.0.0.0/8',         // "this network"
        '10.0.0.0/8',        // private
        '100.64.0.0/10',     // CGNAT (covers cloud metadata adjacency on some carriers)
        '127.0.0.0/8',       // loopback
        '169.254.0.0/16',    // link-local (AWS/GCP metadata 169.254.169.254 lives here)
        '172.16.0.0/12',     // private
        '192.0.0.0/24',      // IETF protocol
        '192.0.2.0/24',      // TEST-NET-1
        '192.88.99.0/24',    // 6to4 anycast
        '192.168.0.0/16',    // private
        '198.18.0.0/15',     // benchmark
        '198.51.100.0/24',   // TEST-NET-2
        '203.0.113.0/24',    // TEST-NET-3
        '224.0.0.0/4',       // multicast
        '240.0.0.0/4',       // reserved
        '255.255.255.255/32',// broadcast
    ];

    /**
     * Throws InvalidArgumentException with a non-leaky message if the URL is
     * unsafe. Returns the canonical URL string if safe.
     */
    public static function assertSafe(string $url, array $opts = []): string
    {
        $allowHttp = $opts['allow_http'] ?? false;

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('URL is malformed.');
        }

        $scheme = strtolower($parts['scheme']);
        $host   = strtolower($parts['host']);
        $port   = $parts['port'] ?? null;

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            throw new InvalidArgumentException('URL must not contain userinfo.');
        }

        if (! in_array($scheme, $allowHttp ? ['http', 'https'] : ['https'], true)) {
            throw new InvalidArgumentException('URL scheme must be https.');
        }

        if ($port !== null) {
            $defaultPort = $scheme === 'https' ? 443 : 80;
            if ((int) $port !== $defaultPort) {
                throw new InvalidArgumentException('URL must use the default port.');
            }
        }

        if ($host === '' || $host[0] === '[') {
            // IPv6 literal — we don't support these (rebinding + scope-id risk).
            throw new InvalidArgumentException('IPv6 literal hosts are not allowed.');
        }

        // If the host parses as an IPv4 literal, validate directly.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (! self::isPublicIPv4($host)) {
                throw new InvalidArgumentException('URL resolves to a non-public address.');
            }
            return $url;
        }

        // Hostname — must resolve, AND every resolved A record must be public.
        // gethostbynamel returns false when DNS fails (which we treat as unsafe).
        $records = @gethostbynamel($host);
        if (! is_array($records) || $records === []) {
            throw new InvalidArgumentException('URL host did not resolve.');
        }
        foreach ($records as $ip) {
            if (! self::isPublicIPv4($ip)) {
                throw new InvalidArgumentException('URL resolves to a non-public address.');
            }
        }

        return $url;
    }

    public static function isSafe(string $url, array $opts = []): bool
    {
        try {
            self::assertSafe($url, $opts);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function isPublicIPv4(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        // Rejects all RFC1918/loopback/link-local AND reserved ranges.
        // We add CGNAT explicitly below because PHP's FILTER_FLAG_NO_PRIV_RANGE
        // does not include 100.64.0.0/10.
        $cleanByFilter = (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if (! $cleanByFilter) {
            return false;
        }
        foreach (self::PRIVATE_CIDRS as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return false;
            }
        }
        return true;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }
        $mask = -1 << (32 - $bits);
        return (($ipLong & $mask) === ($subnetLong & $mask));
    }
}
