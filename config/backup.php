<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup do banco e dos arquivos
    |--------------------------------------------------------------------------
    |
    | O comando `php artisan backup:run` gera um dump SQL compactado (.sql.gz) e
    | um .zip dos arquivos enviados pelos usuários. É agendado em routes/console.php
    | e exige o cron do Laravel (`schedule:run` a cada minuto) no servidor.
    |
    | Um backup que fica só no mesmo servidor não protege contra perda do servidor:
    | configure BACKUP_COPY_DISK com um disco externo (S3, SFTP...) ou baixe as
    | cópias periodicamente.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', true),

    // Pasta privada (fora de public/) onde os backups são gravados.
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    // Retenção: apaga o que é mais velho que N dias, sempre mantendo ao menos "keep_min" conjuntos.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'keep_min' => (int) env('BACKUP_KEEP_MIN', 3),

    // Disco opcional (config/filesystems.php) que recebe uma cópia de cada backup.
    'copy_disk' => env('BACKUP_COPY_DISK'),

    // Chave para criptografar os backups em repouso (php artisan backup:key). Vazia = sem criptografia.
    // Guarde uma cópia da chave FORA do servidor: sem ela os backups cifrados não podem ser restaurados.
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', ''),

    // E-mail avisado quando o backup automático falha.
    'notify_email' => env('BACKUP_NOTIFY_EMAIL', ''),

    // Horário diário (fuso da aplicação).
    'time' => env('BACKUP_TIME', '02:30'),

    // O app:preflight reprova se o último backup for mais antigo que isto.
    'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 36),

    // Tabelas cuja estrutura é salva, mas cujos dados são descartáveis.
    'skip_data' => ['sessions', 'cache', 'cache_locks'],

    // Pastas arquivadas no .zip.
    'file_dirs' => [
        storage_path('app/private'),
        storage_path('app/public'),
    ],
];
