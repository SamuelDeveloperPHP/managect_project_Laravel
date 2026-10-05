<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'cpf')) {
            return;
        }

        $rows = DB::table('users')->whereNotNull('cpf')->get(['id', 'cpf']);
        $normalizedById = [];
        foreach ($rows as $row) {
            $digits = preg_replace('/\D+/', '', (string) $row->cpf) ?? '';
            if ($digits !== '') {
                $normalizedById[$row->id] = $digits;
            }
        }

        $duplicateExists = collect($normalizedById)->duplicates()->isNotEmpty();
        if ($duplicateExists) {
            throw new RuntimeException('Há CPFs duplicados após normalização. Corrija os membros antes de aplicar esta migration.');
        }

        foreach ($rows as $row) {
            $normalized = $normalizedById[$row->id] ?? null;
            if ($row->cpf !== $normalized) {
                DB::table('users')->where('id', $row->id)->update(['cpf' => $normalized]);
            }
        }

        if (! Schema::hasIndex('users', 'users_cpf_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('cpf', 'users_cpf_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasIndex('users', 'users_cpf_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_cpf_unique');
            });
        }
    }
};
