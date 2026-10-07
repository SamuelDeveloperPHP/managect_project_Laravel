<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Preflight extends Command
{
    protected $signature = 'app:preflight';

    protected $description = 'Confere a configuração de produção (debug, URL, e-mail, sessão, backup). Retorna erro se algo crítico falhar.';

    public function handle(): int
    {
        $production = app()->environment('production');
        $rows = [];
        $failed = false;

        $check = function (string $name, bool $ok, string $detail, bool $critical = true) use (&$rows, &$failed, $production): void {
            $status = $ok ? 'OK' : ($critical && $production ? 'FALHA' : 'AVISO');
            if ($status === 'FALHA') {
                $failed = true;
            }
            $rows[] = [$status, $name, $detail];
        };

        $check('Ambiente', $production, 'APP_ENV='.app()->environment().($production ? '' : ' (os testes abaixo só reprovam em production)'), false);
        $check('APP_DEBUG desligado', ! config('app.debug'), config('app.debug') ? 'APP_DEBUG=true expõe erros internos' : 'desligado');

        $url = (string) config('app.url');
        $check('APP_URL com https', str_starts_with($url, 'https://') && ! str_contains($url, 'localhost'), $url);

        $mailer = (string) config('mail.default');
        $check('E-mail entrega de verdade', ! in_array($mailer, ['log', 'array'], true), "MAIL_MAILER={$mailer}");
        $from = (string) config('mail.from.address');
        $check('Remetente real', $from !== '' && ! str_contains($from, 'example.com'), "MAIL_FROM_ADDRESS={$from}");

        $check('Cookie de sessão seguro', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE');
        $check('Sessão criptografada', (bool) config('session.encrypt'), 'SESSION_ENCRYPT');
        $check('Banco de produção', config('database.default') !== 'sqlite', 'DB_CONNECTION='.config('database.default'), false);
        $check('Link storage', is_link(public_path('storage')) || is_dir(public_path('storage')), 'php artisan storage:link', false);

        $this->backupChecks($check);

        $this->table(['Resultado', 'Verificação', 'Detalhe'], $rows);
        $failed ? $this->error('Há itens críticos para corrigir antes de publicar.') : $this->info('Nenhum item crítico pendente.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function backupChecks(callable $check): void
    {
        if (! config('backup.enabled')) {
            $check('Backup ativado', false, 'BACKUP_ENABLED=false');

            return;
        }

        $marker = rtrim((string) config('backup.path'), '/\\').DIRECTORY_SEPARATOR.'last-success.json';
        $last = is_file($marker) ? json_decode((string) File::get($marker), true) : null;
        $finished = isset($last['finished_at']) ? \Carbon\Carbon::parse($last['finished_at']) : null;
        $max = (int) config('backup.max_age_hours');

        $check(
            "Backup recente (até {$max} h)",
            $finished !== null && $finished->gt(now()->subHours($max)),
            $finished ? 'último: '.$finished->format('d/m/Y H:i') : 'nenhum backup encontrado — rode php artisan backup:run e confira o cron',
        );
        $check('Cópia externa do backup', (bool) config('backup.copy_disk'), config('backup.copy_disk') ? 'disco: '.config('backup.copy_disk') : 'BACKUP_COPY_DISK vazio: backup só neste servidor', false);
    }
}
