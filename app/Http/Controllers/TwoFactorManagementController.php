<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Ativar, regenerar códigos de recuperação e desligar o segundo fator (sempre pelo próprio usuário logado). */
class TwoFactorManagementController extends Controller
{
    /** Passo 1: gera o segredo (ainda NÃO ativo) e volta ao Perfil, que mostra o QR code. */
    public function start(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => 'O segundo fator já está ativo.']);
        }

        $user->forceFill([
            'two_factor_secret' => $twoFactor->newSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return back();
    }

    /** Passo 2: só ativa depois de provar que o app gera códigos corretos. Mostra os códigos de recuperação UMA vez. */
    public function confirm(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => 'Inicie a ativação primeiro.']);
        }

        $step = $twoFactor->verify((string) $user->two_factor_secret, $data['code'], null);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'Código inválido. Confira se escaneou o QR code certo e se a hora do celular está correta.']);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
        ])->save();
        $this->audit($request, $user, 'auth.two_factor_enabled', 'Segundo fator ativado');

        return back()->with('recovery_codes', $codes);
    }

    /** Cancela uma ativação que ficou pela metade (segredo gerado, mas nunca confirmado). */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
        }

        return back();
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $request->user();
        $this->requireEnabled($user);
        $this->requirePassword($request, $user);

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes)])->save();
        $this->audit($request, $user, 'auth.two_factor_codes_regenerated', 'Códigos de recuperação gerados novamente');

        return back()->with('recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->requireEnabled($user);

        if ($user->requiresTwoFactor()) {
            throw ValidationException::withMessages(['password' => 'Seu perfil exige o segundo fator: ele não pode ser desligado.']);
        }
        $this->requirePassword($request, $user);

        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
        ])->save();
        $this->audit($request, $user, 'auth.two_factor_disabled', 'Segundo fator desativado');

        return back()->with('success', 'Segundo fator desativado.');
    }

    private function requireEnabled(User $user): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['password' => 'Ative o segundo fator primeiro.']);
        }
    }

    private function requirePassword(Request $request, User $user): void
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
        }
    }

    private function audit(Request $request, User $user, string $action, string $description): void
    {
        AuditLog::query()->create([
            'user_id' => $user->getKey(), 'company_id' => $user->company_id, 'action' => $action, 'entity_type' => 'users',
            'entity_id' => $user->getKey(), 'description' => $description, 'route_name' => $request->route()?->getName(),
            'method' => $request->method(), 'path' => '/'.$request->path(), 'status_code' => 200, 'outcome' => 'success',
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'created_at' => now(),
        ]);
    }
}
