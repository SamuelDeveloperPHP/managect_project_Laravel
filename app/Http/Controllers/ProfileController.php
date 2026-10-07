<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, TwoFactor $twoFactor): Response
    {
        $user = $request->user();
        // Ativação pela metade: o segredo existe, mas ainda não foi confirmado com um código do app.
        $pending = $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled();

        return Inertia::render('Profile/Edit', [
            'twoFactor' => [
                'enabled' => $user->hasTwoFactorEnabled(),
                'required' => $user->requiresTwoFactor(),
                'recovery_codes_left' => $user->hasTwoFactorEnabled() ? count((array) $user->two_factor_recovery_codes) : 0,
                'setup' => $pending ? [
                    'qr_svg' => $twoFactor->qrCodeSvg($user, (string) $user->two_factor_secret),
                    'secret' => trim(chunk_split((string) $user->two_factor_secret, 4, ' ')),
                ] : null,
            ],
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $photo = $data['photo'] ?? null;
        unset($data['photo']);

        $previousPhoto = $user->profile_photo_path;
        $newPhoto = $photo?->store('profile-photos', 'public');
        if ($photo && ! is_string($newPhoto)) {
            throw new \RuntimeException('Não foi possível armazenar a foto do perfil.');
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($newPhoto) {
            $user->profile_photo_path = $newPhoto;
        }

        try {
            $user->save();
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $exception;
        }

        if ($newPhoto && $previousPhoto) {
            Storage::disk('public')->delete($previousPhoto);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->role === 'master') {
            throw ValidationException::withMessages(['password' => 'A conta master não pode ser removida por este fluxo.']);
        }

        if ($user->role === 'admin' && User::query()
            ->where('company_id', $user->company_id)
            ->where('role', 'admin')->where('is_active', true)
            ->whereKeyNot($user->id)->count() === 0) {
            throw ValidationException::withMessages(['password' => 'A empresa precisa manter pelo menos um administrador ativo.']);
        }

        Auth::logout();

        $user->forceFill(['is_active' => false])->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
