<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $sig = $request->input('signature', []);
        $token     = (string) ($sig['token']     ?? '');
        $timestamp = (string) ($sig['timestamp'] ?? '');
        $hash      = (string) ($sig['signature'] ?? '');

        if ($token === '' || $timestamp === '' || $hash === '') {
            return response()->json(['error' => 'missing_signature'], 401);
        }

        // Reject replays older than 15 min
        if (abs(time() - (int) $timestamp) > 900) {
            return response()->json(['error' => 'signature_expired'], 401);
        }

        $secret = (string) config('services.mailgun.webhook_signing_key');
        if ($secret === '' || str_starts_with($secret, 'secret://')) {
            return response()->json(['error' => 'webhook_signing_key_missing'], 500);
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $secret);
        if (! hash_equals($expected, $hash)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        return $next($request);
    }
}
