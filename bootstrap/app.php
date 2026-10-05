<?php

use App\Http\Middleware\AuditUserActivity;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestCorrelation;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(FrameworkTrustProxies::class, TrustConfiguredProxies::class);

        $middleware->web(append: [
            RequestCorrelation::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AuditUserActivity::class,
        ]);

        $middleware->alias([
            'company' => SetCurrentCompany::class,
            'role' => RequireRole::class,
            'permission' => RequirePermission::class,
        ]);

        // Route model binding (Project $project) must see the active company, so SetCurrentCompany runs before SubstituteBindings.
        $middleware->priority([
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            SetCurrentCompany::class,
            SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(fn ($response) => app(SecurityHeaders::class)->apply(request(), $response));
    })->create();
