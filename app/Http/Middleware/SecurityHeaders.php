<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && $request->isSecure()) {
            $nonce = base64_encode(random_bytes(32));
            Vite::useCspNonce($nonce);
            $request->attributes->set('csp_nonce', $nonce);
        }

        return $this->apply($request, $next($request));
    }

    public function apply(Request $request, Response $response): Response
    {
        if ($requestId = $request->attributes->get('request_id')) {
            $response->headers->set('X-Request-ID', (string) $requestId);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');

            $nonce = (string) $request->attributes->get('csp_nonce');
            if ($nonce !== '') {
                $response->headers->set('Content-Security-Policy', implode('; ', [
                    "default-src 'self'",
                    "base-uri 'self'",
                    "object-src 'none'",
                    "frame-ancestors 'none'",
                    "form-action 'self'",
                    "script-src 'self' 'nonce-{$nonce}'",
                    "style-src 'self' 'nonce-{$nonce}' https://fonts.bunny.net",
                    "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com data:",
                    "img-src 'self' data: blob:",
                    "connect-src 'self'",
                    'upgrade-insecure-requests',
                ]));
            }
        }

        if ($request->user() !== null || $request->is('login', 'forgot-password', 'reset-password/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
