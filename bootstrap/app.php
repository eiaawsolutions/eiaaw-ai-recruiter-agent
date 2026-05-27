<?php

use App\Http\Middleware\EnforceTenantScope;
use App\Http\Middleware\VerifyApiKey;
use App\Http\Middleware\VerifyResendSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        // SecretsServiceProvider MUST register first so `secret://` handles
        // are resolved before any other provider reads its config (per EIAAW
        // Deploy Contract).
        App\Providers\SecretsServiceProvider::class,
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\AgentServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.key'           => VerifyApiKey::class,
            'tenant.scope'      => EnforceTenantScope::class,
            'resend.signature'  => VerifyResendSignature::class,
        ]);

        // Sanctum SPA: routes under api/v1/spa/* run with the full session
        // + cookie + CSRF stack so cookie-session auth actually works. Sanctum's
        // stateful middleware then promotes the session user to the API guard
        // for matching origins. Non-SPA api/* paths stay stateless.
        $middleware->group('spa', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // CSRF exemption for inbound webhooks (signature-protected separately)
        // and stateless API key endpoints (no session/cookie at all).
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'api/v1/jobs*',
            'api/v1/candidates*',
            'api/v1/outreach*',
            'api/v1/webhook-endpoints*',
            'api/v1/handoff/*',
            'api/v1/health',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, $request) {
            if (! ($request->is('api/*') || $request->is('webhooks/*') || $request->wantsJson())) {
                return null;
            }

            // Validation: let Laravel render its own 422 JSON envelope.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error'  => ['type' => 'ValidationException', 'message' => $e->getMessage()],
                    'errors' => $e->errors(),
                ], 422);
            }
            // Auth
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json(['error' => ['type' => 'Unauthenticated', 'message' => 'Unauthenticated.']], 401);
            }
            // Model not found / 404
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['error' => ['type' => 'NotFound', 'message' => 'Resource not found.']], 404);
            }
            // Anything carrying an HTTP status (HttpException, etc.)
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                return response()->json([
                    'error' => ['type' => class_basename($e), 'message' => $e->getMessage() ?: 'Error.'],
                ], $status);
            }

            // Genuine 500
            return response()->json([
                'error' => [
                    'type'    => class_basename($e),
                    'message' => app()->isProduction() ? 'Internal error.' : $e->getMessage(),
                ],
            ], 500);
        });
    })
    ->create();
