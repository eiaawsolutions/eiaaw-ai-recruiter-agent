<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA endpoints — used by React/Vue/Svelte consoles that want
 * cookie-session auth (matching the operator console) instead of long-lived
 * Bearer API keys.
 *
 * Flow:
 *   1. GET /sanctum/csrf-cookie         (Sanctum provides)
 *   2. POST /api/v1/spa/login           (this controller — sets the session)
 *   3. GET  /api/v1/spa/me              (returns the user + tenant)
 *   4. POST /api/v1/spa/tokens          (mint a personal token for embedded clients)
 *   5. POST /api/v1/spa/logout          (clears the session)
 */
class SpaAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $creds = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::query()->withoutGlobalScopes()->where('email', $creds['email'])->first();

        // Always run a Hash::check, even when the user doesn't exist, so the
        // request timing does not leak account existence to attackers.
        $dummyHash = '$2y$10$AbsoluteDummyHashAbsoluteDummyHashAbsoluteDummyHashAbso';
        $passwordOk = Hash::check(
            $creds['password'],
            $user?->password ?: $dummyHash,
        );

        if (! $user || ! $passwordOk || ! $user->is_active) {
            // Single generic error — never reveal "no such user" vs "wrong password".
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json(['data' => $this->presentUser($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        return response()->json(['data' => $this->presentUser($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Mint a Sanctum personal access token bound to the authenticated user.
     * Distinct from machine-to-machine ApiKey rows — these are user-scoped
     * and respect the user's tenant.
     */
    public function createToken(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'name'      => 'required|string|max:120',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|max:60',
        ]);

        $token = $user->createToken($data['name'], $data['abilities'] ?? ['*']);

        return response()->json([
            'data' => [
                'name'       => $data['name'],
                'token'      => $token->plainTextToken,
                'abilities'  => $token->accessToken->abilities,
                'created_at' => $token->accessToken->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function listTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        return response()->json([
            'data' => $user->tokens()->latest()->get()->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'abilities'    => $t->abilities,
                'last_used_at' => optional($t->last_used_at)->toIso8601String(),
                'created_at'   => $t->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function revokeToken(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $user->tokens()->where('id', $id)->delete();
        return response()->json(['data' => ['revoked' => true]]);
    }

    private function presentUser(User $u): array
    {
        return [
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'role'  => $u->role,
            'tenant' => [
                'id'         => $u->tenant?->public_id,
                'name'       => $u->tenant?->name,
                'slug'       => $u->tenant?->slug,
                'timezone'   => $u->tenant?->timezone,
                'brand_voice' => $u->tenant?->brand_voice,
            ],
        ];
    }
}
