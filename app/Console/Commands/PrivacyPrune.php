<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrivacyPrune extends Command
{
    protected $signature = 'privacy:prune {--dry-run : Só conta o que seria afetado}';

    protected $description = 'LGPD: tira IP e navegador dos registros de atividade antigos e apaga os que passaram do prazo de retenção.';

    public function handle(): int
    {
        $ipDays = max(1, (int) config('privacy.ip_retention_days'));
        $auditDays = max($ipDays, (int) config('privacy.audit_retention_days'));
        $dry = (bool) $this->option('dry-run');

        $ipCutoff = now()->subDays($ipDays);
        $auditCutoff = now()->subDays($auditDays);

        $withIp = DB::table('audit_logs')->where('created_at', '<', $ipCutoff)
            ->where(fn ($query) => $query->whereNotNull('ip_address')->orWhereNotNull('user_agent'));
        $expired = DB::table('audit_logs')->where('created_at', '<', $auditCutoff);

        $ipCount = (clone $withIp)->count();
        $expiredCount = (clone $expired)->count();

        if (! $dry) {
            $withIp->update(['ip_address' => null, 'user_agent' => null]);
            $expired->delete();
        }

        $this->info(($dry ? '[simulação] ' : '')."IP/navegador removidos de {$ipCount} registro(s) com mais de {$ipDays} dias; {$expiredCount} registro(s) com mais de {$auditDays} dias ".($dry ? 'seriam apagados.' : 'apagados.'));

        return self::SUCCESS;
    }
}
