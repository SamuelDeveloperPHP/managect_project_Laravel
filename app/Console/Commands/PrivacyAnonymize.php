<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Privacy\UserAnonymizer;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PrivacyAnonymize extends Command
{
    protected $signature = 'privacy:anonymize {email : E-mail da conta} {--force : Não pede confirmação}';

    protected $description = 'LGPD: anonimiza os dados pessoais de uma conta (pedido do titular atendido pelo servidor). Irreversível.';

    public function handle(UserAnonymizer $anonymizer): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::withoutGlobalScopes()->withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("Nenhuma conta com o e-mail {$email}.");

            return self::FAILURE;
        }
        if ($user->anonymized_at !== null) {
            $this->info('Esta conta já foi anonimizada.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("Apagar de forma IRREVERSÍVEL os dados pessoais de {$user->name} <{$user->email}>?")) {
            $this->line('Cancelado.');

            return self::FAILURE;
        }

        try {
            $anonymizer->anonymize($user, null, 'servidor (artisan)');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Dados pessoais anonimizados.');

        return self::SUCCESS;
    }
}
