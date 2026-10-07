<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only('id', 'name', 'email', 'role', 'permissions', 'email_verified_at'),
                    'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                    'two_factor_required' => $user->mustEnrollTwoFactor(),
                    'profile_photo_url' => $user->profile_photo_path ? '/storage/'.ltrim($user->profile_photo_path, '/') : null,
                ] : null,
                'company' => ($request->attributes->get('current_company') ?? $request->user()?->company)?->only('id', 'name'),
                'selected_company_id' => $request->session()->get('master_company_id'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'recovery_codes' => fn () => $request->session()->get('recovery_codes'),
            ],
        ];
    }
}
