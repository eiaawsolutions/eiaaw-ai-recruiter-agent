<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current user's tenant for UI requests. API requests bind via
 * VerifyApiKey instead.
 */
class EnforceTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user && $user->tenant_id) {
            TenantContext::bindById($user->tenant_id);
        }
        return $next($request);
    }
}
