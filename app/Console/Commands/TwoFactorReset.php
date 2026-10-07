<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

class TwoFactorReset extends Command
{
    protected $signature = 'two-factor:reset {email : E-mail da conta} {--force : Não pede confirmação}';

    protected $description = 'Apaga o segundo fator de uma conta (perda do celular e dos códigos). A pessoa ativa de novo no próximo acesso. Use no servidor.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("Nenhuma conta com o e-mail {$email}.");

            return self::FAILURE;
        }

        if (! $user->hasTwoFactorEnabled() && $user->two_factor_secret === null) {
            $this->info('Esta conta não tem segundo fator ativo. Nada a fazer.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Apagar o segundo fator de {$user->name} <{$user->email}>?")) {
            $this->line('Cancelado.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
        ])->save();

        AuditLog::query()->create([
            'user_id' => null, 'company_id' => $user->company_id, 'action' => 'auth.two_factor_reset', 'entity_type' => 'users',
            'entity_id' => $user->getKey(), 'description' => 'Segundo fator redefinido pelo servidor (artisan) para '.$user->email,
            'method' => 'CLI', 'path' => 'artisan two-factor:reset', 'status_code' => null, 'outcome' => 'success', 'created_at' => now(),
        ]);

        $this->info('Segundo fator apagado. No próximo acesso a pessoa será levada a ativar de novo.');

        return self::SUCCESS;
    }
}
