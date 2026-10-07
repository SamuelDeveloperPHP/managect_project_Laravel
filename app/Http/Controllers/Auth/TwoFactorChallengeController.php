<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Segunda etapa do login: código do app autenticador ou código de recuperação. */
class TwoFactorChallengeController extends Controller
{
    private const MAX_PER_USER_AND_IP = 5;

    private const MAX_PER_USER = 10;

    private const DECAY_SECONDS = 900;

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if ($user === null) {
            return redirect()->route('login')->withErrors(['email' => 'A verificação expirou. Entre novamente com e-mail e senha.']);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);
        if (blank($data['code'] ?? null) && blank($data['recovery_code'] ?? null)) {
            throw $this->challengeFailure('Informe o código do aplicativo ou um código de recuperação.');
        }

        $this->ensureNotLocked($request, $user);

        $viaRecovery = filled($data['recovery_code'] ?? null);
        $accepted = $viaRecovery
            ? $this->consumeRecoveryCode($user, (string) $data['recovery_code'], $twoFactor)
            : $this->acceptAuthenticatorCode($user, (string) $data['code'], $twoFactor);

        if (! $accepted) {
            RateLimiter::hit($this->ipKey($request, $user), self::DECAY_SECONDS);
            RateLimiter::hit($this->userKey($user), self::DECAY_SECONDS);
            $this->audit($request, $user, 'auth.two_factor_failed', 'Código de segundo fator recusado'.($viaRecovery ? ' (recuperação)' : ''), 401, 'denied');

            throw $this->challengeFailure($viaRecovery ? 'Código de recuperação inválido ou já utilizado.' : 'Código inválido ou expirado. Confira a hora do celular e tente de novo.');
        }

        $pending = $request->session()->pull('two_factor.pending');
        RateLimiter::clear($this->ipKey($request, $user));
        RateLimiter::clear($this->userKey($user));

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        LoginRequest::completeLogin($user);

        if ($viaRecovery) {
            $this->audit($request, $user, 'auth.two_factor_recovery_used', 'Entrou com um código de recuperação', 200, 'success');
            $remaining = count((array) $user->fresh()->two_factor_recovery_codes);

            return redirect()->intended(route('dashboard', absolute: false))->with(
                'warning',
                $remaining === 0
                    ? 'Você usou seu último código de recuperação. Gere novos no Perfil.'
                    : "Você entrou com um código de recuperação. Restam {$remaining}; gere novos no Perfil se precisar.",
            );
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function acceptAuthenticatorCode(User $user, string $code, TwoFactor $twoFactor): bool
    {
        $step = $twoFactor->verify((string) $user->two_factor_secret, $code, $user->two_factor_last_step);
        if ($step === null) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    private function consumeRecoveryCode(User $user, string $code, TwoFactor $twoFactor): bool
    {
        $remaining = $twoFactor->consumeRecoveryCode((array) $user->two_factor_recovery_codes, $code);
        if ($remaining === null) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    /** O usuário que passou pela senha e aguarda o segundo fator (ou null se não há desafio válido). */
    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor.pending');
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('two_factor.pending');

            return null;
        }

        $user = User::query()->where('is_active', true)->find($pending['id'] ?? 0);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('two_factor.pending');

            return null;
        }

        return $user;
    }

    private function ensureNotLocked(Request $request, User $user): void
    {
        if (! RateLimiter::tooManyAttempts($this->ipKey($request, $user), self::MAX_PER_USER_AND_IP)
            && ! RateLimiter::tooManyAttempts($this->userKey($user), self::MAX_PER_USER)) {
            return;
        }

        $request->session()->forget('two_factor.pending');
        $this->audit($request, $user, 'auth.two_factor_locked', 'Segundo fator bloqueado por excesso de tentativas', 429, 'denied');
        $minutes = (int) ceil(max(RateLimiter::availableIn($this->ipKey($request, $user)), RateLimiter::availableIn($this->userKey($user))) / 60);

        throw ValidationException::withMessages([
            'code' => "Muitas tentativas. Aguarde {$minutes} minuto(s) e entre novamente.",
        ])->redirectTo(route('login'));
    }

    private function challengeFailure(string $message): ValidationException
    {
        return ValidationException::withMessages(['code' => $message])->redirectTo(route('two-factor.challenge'));
    }

    private function ipKey(Request $request, User $user): string
    {
        return 'auth:2fa:user-ip:'.$user->getKey().':'.hash('sha256', (string) $request->ip());
    }

    private function userKey(User $user): string
    {
        return 'auth:2fa:user:'.$user->getKey();
    }

    private function audit(Request $request, User $user, string $action, string $description, int $status, string $outcome): void
    {
        AuditLog::query()->create([
            'user_id' => $user->getKey(), 'company_id' => $user->company_id, 'action' => $action, 'entity_type' => 'users',
            'entity_id' => $user->getKey(), 'description' => $description, 'route_name' => $request->route()?->getName(),
            'method' => $request->method(), 'path' => '/'.$request->path(), 'status_code' => $status, 'outcome' => $outcome,
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'created_at' => now(),
        ]);
    }
}
