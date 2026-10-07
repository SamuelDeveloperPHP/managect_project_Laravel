<?php

namespace App\Console\Commands;

use App\Support\Backup\BackupCrypto;
use Illuminate\Console\Command;

class BackupKey extends Command
{
    protected $signature = 'backup:key';

    protected $description = 'Gera uma chave para criptografar os backups (BACKUP_ENCRYPTION_KEY). Guarde-a fora do servidor.';

    public function handle(): int
    {
        $this->line('BACKUP_ENCRYPTION_KEY='.BackupCrypto::generateKey());
        $this->newLine();
        $this->warn('Copie para o .env E guarde uma cópia em local seguro (gerenciador de senhas). Sem a chave, os backups criptografados NÃO podem ser restaurados.');

        return self::SUCCESS;
    }
}
