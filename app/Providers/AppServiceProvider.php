<?php

namespace App\Providers;

use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceProductionSecurityConfiguration();

        Vite::prefetch(concurrency: 3);

        RateLimiter::for('login-request', fn (Request $request) => Limit::perMinute(30)
            ->by('login-request:'.$request->ip()));

        RateLimiter::for('password-reset-request', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('password-reset-ip:'.$request->ip()),
                Limit::perHour(5)->by('password-reset-email:'.hash_hmac('sha256', $email, (string) config('app.key'))),
            ];
        });

        RateLimiter::for('password-reset-submit', fn (Request $request) => Limit::perMinute(10)
            ->by('password-reset-submit:'.$request->ip()));

        RateLimiter::for('sensitive-account-action', function (Request $request): Limit {
            $actor = $request->user();
            $identity = $actor ? 'user:'.$actor->getAuthIdentifier() : 'guest';

            return Limit::perMinute(10)->by('sensitive-account-action:'.$identity.':'.$request->ip());
        });

        RateLimiter::for('authenticated-web', function (Request $request): Limit {
            $actor = $request->user();
            $identity = $actor ? 'user:'.$actor->getAuthIdentifier() : 'guest';

            return Limit::perMinute(120)->by('authenticated-web:'.$identity.':'.$request->ip());
        });

        PasswordRule::defaults(fn () => PasswordRule::min(12));
    }

    private function enforceProductionSecurityConfiguration(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $appUrlScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        $databaseUsername = (string) config('database.connections.'.config('database.default').'.username', '');
        $checks = [
            'APP_KEY must be set' => trim((string) config('app.key')) !== '',
            'APP_DEBUG must be false' => config('app.debug') === false,
            'APP_URL must use HTTPS' => $appUrlScheme === 'https',
            'SESSION_SECURE_COOKIE must be true' => config('session.secure') === true,
            'SESSION_ENCRYPT must be true' => config('session.encrypt') === true,
            'RATE_LIMITER_STORE must be redis' => config('cache.limiter') === 'redis',
            'the runtime DB user must not be root' => strtolower($databaseUsername) !== 'root',
        ];

        $missing = array_keys(array_filter($checks, fn (bool $ready): bool => ! $ready));
        if ($missing !== []) {
            throw new \LogicException('Unsafe production configuration: '.implode('; ', $missing));
        }
    }
}
