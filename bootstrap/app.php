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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(fn ($response) => app(SecurityHeaders::class)->apply(request(), $response));
    })->create();
