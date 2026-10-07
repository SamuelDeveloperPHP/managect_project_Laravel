<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTest extends Command
{
    protected $signature = 'mail:test {to : E-mail que receberá a mensagem de teste}';

    protected $description = 'Envia um e-mail de teste com a configuração atual e mostra o motivo se falhar.';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Informe um endereço de e-mail válido.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host", '-');
        $port = (string) config("mail.mailers.{$mailer}.port", '-');
        $this->line("Mailer: {$mailer} · servidor: {$host}:{$port} · remetente: ".config('mail.from.address'));

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("O mailer \"{$mailer}\" não entrega e-mails de verdade (só grava no log). Configure MAIL_MAILER=smtp.");
        }

        try {
            Mail::raw(
                'Teste de envio do Trilha+ ('.config('app.url').') em '.now()->format('d/m/Y H:i').".\nSe você recebeu esta mensagem, a recuperação de senha por e-mail vai funcionar.",
                fn ($message) => $message->to($to)->subject('Trilha+ · teste de e-mail'),
            );
        } catch (\Throwable $exception) {
            $this->error('Falha no envio: '.$exception->getMessage());
            $this->line('Confira host, porta, usuário, senha, MAIL_SCHEME (smtps para a porta 465) e se o remetente existe no provedor.');

            return self::FAILURE;
        }

        $this->info("Mensagem entregue ao servidor de e-mail para {$to}. Confira a caixa de entrada e o spam.");

        return self::SUCCESS;
    }
}
