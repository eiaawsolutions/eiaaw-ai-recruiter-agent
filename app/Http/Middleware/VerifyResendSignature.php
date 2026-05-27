<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies a Resend webhook signature.
 *
 * Resend uses Svix to sign webhooks. Each delivery carries:
 *   svix-id        — unique message id
 *   svix-timestamp — unix seconds (we reject anything older than 5 min)
 *   svix-signature — space-separated list of "v1,<base64hash>" pairs
 *
 * The hash is HMAC-SHA256 over `<svix-id>.<svix-timestamp>.<raw-body>` keyed
 * with the SIGNING SECRET. Resend signing secrets look like `whsec_xxxxx` —
 * the actual key material is the base64-decoded portion after `whsec_`.
 */
class VerifyResendSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $svixId   = (string) $request->header('svix-id', '');
        $svixTs   = (string) $request->header('svix-timestamp', '');
        $svixSigs = (string) $request->header('svix-signature', '');

        if ($svixId === '' || $svixTs === '' || $svixSigs === '') {
            return response()->json(['error' => 'missing_signature'], 401);
        }

        if (! ctype_digit($svixTs) || abs(time() - (int) $svixTs) > 300) {
            return response()->json(['error' => 'signature_expired'], 401);
        }

        $secret = (string) config('services.resend.webhook_signing_secret');
        if ($secret === '' || str_starts_with($secret, 'secret://')) {
            Log::error('VerifyResendSignature: signing secret not configured.');
            return response()->json(['error' => 'webhook_signing_secret_missing'], 500);
        }

        // Strip the `whsec_` prefix and base64-decode to get the raw HMAC key.
        $rawKey = str_starts_with($secret, 'whsec_')
            ? base64_decode(substr($secret, 6), true)
            : $secret;
        if ($rawKey === false || $rawKey === '') {
            return response()->json(['error' => 'webhook_signing_secret_invalid'], 500);
        }

        $payload  = $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', "{$svixId}.{$svixTs}.{$payload}", $rawKey, true));

        // svix-signature is "v1,<sig> v1,<sig2> ..." — match any.
        $matched = false;
        foreach (preg_split('/\s+/', $svixSigs) as $entry) {
            if (! str_contains($entry, ',')) continue;
            [$version, $given] = explode(',', $entry, 2);
            if ($version === 'v1' && hash_equals($expected, $given)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        return $next($request);
    }
}
