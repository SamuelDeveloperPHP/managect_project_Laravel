<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies(): array|string|null
    {
        $proxies = config('security.trusted_proxies', []);

        return $proxies === [] ? null : $proxies;
    }

    protected function headers(): int
    {
        return Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO;
    }
}
