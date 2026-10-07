<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário do banco e dos arquivos. Exige o cron do Laravel no servidor:
//   * * * * * cd /caminho/da/aplicacao && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('backup:run')
    ->dailyAt((string) config('backup.time', '02:30'))
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/backup.log'));

// Prova semanal de que o último backup é utilizável (checksum + leitura completa + contagem de linhas).
Schedule::command('backup:verify')
    ->weeklyOn(0, '03:30')
    ->appendOutputTo(storage_path('logs/backup.log'));
