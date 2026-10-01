<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelation
{
    public function handle(Request $request, Closure $next): Response
    {
        // Never trust a caller-supplied ID: generate a bounded, unguessable value for this request.
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        return $next($request);
    }
}
