<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_FAILED_ATTEMPTS_PER_IP = 12;

    private const MAX_FAILED_ATTEMPTS_PER_EMAIL = 6;

    private const DECAY_SECONDS = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Confere as credenciais. Retorna true se a pessoa entrou; false se falta o segundo fator
     * (nesse caso NINGUÉM foi autenticado ainda: a sessão só guarda um "pendente" de 10 minutos).
     *
     * @throws ValidationException
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        // Valida sem criar sessão: quem tem segundo fator só entra depois do desafio, e uma senha certa
        // sozinha não pode encerrar as sessões "lembrar de mim" da pessoa (o que logout() faria).
        $provider = Auth::guard()->getProvider();
        $credentials = [
            'email' => $this->string('email')->lower()->toString(),
            'password' => (string) $this->input('password'),
            'is_active' => true,
            'deleted_at' => null,
        ];
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            $this->recordFailedAttempt();

            throw $this->loginFailure([
                'email' => trans('auth.failed'),
            ]);
        }

        $isPlatformMaster = $user->isPlatformMasterIdentity();
        $invalidMasterRole = $user->role === 'master' && ! $isPlatformMaster;
        $inactiveCompany = ! $isPlatformMaster && (! $user->company?->is_active || $user->company?->deleted_at !== null);
        if ($invalidMasterRole || $inactiveCompany) {
            $this->recordFailedAttempt();

            throw $this->loginFailure([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->emailThrottleKey());

        if ($user->hasTwoFactorEnabled()) {
            $this->session()->put('two_factor.pending', [
                'id' => $user->getKey(),
                'remember' => $this->boolean('remember'),
                'expires_at' => now()->addMinutes(10)->getTimestamp(),
            ]);

            return false;
        }

        Auth::login($user, $this->boolean('remember'));
        $this->completeLogin($user);

        return true;
    }

    /** Passos comuns a quem entra direto e a quem passou pelo desafio do segundo fator. */
    public static function completeLogin(User $user): void
    {
        if ($user->isPlatformMasterIdentity() && $user->role !== 'master') {
            $user->role = 'master';
            $user->permissions = [];
        }

        $user->forceFill(['last_login_at' => now()])->save();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $blockedFor = max(
            RateLimiter::availableIn($this->ipThrottleKey()),
            RateLimiter::availableIn($this->emailThrottleKey()),
        );
        $ipBlocked = RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::MAX_FAILED_ATTEMPTS_PER_IP);
        $emailBlocked = RateLimiter::tooManyAttempts($this->emailThrottleKey(), self::MAX_FAILED_ATTEMPTS_PER_EMAIL);

        if (! $ipBlocked && ! $emailBlocked) {
            return;
        }

        event(new Lockout($this));

        throw $this->loginFailure([
            'email' => trans('auth.throttle', [
                'seconds' => $blockedFor,
                'minutes' => ceil($blockedFor / 60),
            ]),
        ]);
    }

    /**
     * Erro de login volta SEMPRE para o formulário de login. Sem isso o Laravel volta para a "página anterior" da
     * sessão, que depois de uma navegação do Inertia (home -> Entrar) é a página inicial, e a pessoa nunca vê a mensagem.
     */
    private function loginFailure(array $messages): ValidationException
    {
        return ValidationException::withMessages($messages)->redirectTo(route('login'));
    }

    private function recordFailedAttempt(): void
    {
        RateLimiter::hit($this->ipThrottleKey(), self::DECAY_SECONDS);
        RateLimiter::hit($this->emailThrottleKey(), self::DECAY_SECONDS);
    }

    private function ipThrottleKey(): string
    {
        return 'auth:login:ip:'.hash('sha256', (string) $this->ip());
    }

    private function emailThrottleKey(): string
    {
        $email = Str::lower(trim($this->string('email')->toString()));

        return 'auth:login:email:'.hash_hmac('sha256', $email, (string) config('app.key'));
    }
}
