<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $plaintext = $this->extractKey($request);
        if (! $plaintext) {
            return response()->json(['error' => ['type' => 'Unauthenticated', 'message' => 'Missing API key.']], 401);
        }

        $hash = hash('sha256', $plaintext);
        $key  = ApiKey::query()
            ->withoutGlobalScopes()
            ->where('hash', $hash)
            ->first();

        if (! $key || ! $key->isValid()) {
            return response()->json(['error' => ['type' => 'Unauthenticated', 'message' => 'Invalid or expired API key.']], 401);
        }

        if ($scope && ! $key->hasScope($scope)) {
            return response()->json(['error' => ['type' => 'Forbidden', 'message' => "Key missing scope: {$scope}"]], 403);
        }

        $tenant = $key->tenant()->withoutGlobalScopes()->first();
        if (! $tenant || ! $tenant->is_active) {
            return response()->json(['error' => ['type' => 'TenantSuspended', 'message' => 'Tenant inactive.']], 403);
        }

        TenantContext::bind($tenant);
        $request->attributes->set('api_key', $key);
        $request->attributes->set('tenant', $tenant);

        // Update last_used_at lazily — best-effort, no transaction.
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $auth = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        $alt = $request->header('X-API-Key');
        return $alt ? trim((string) $alt) : null;
    }
}
