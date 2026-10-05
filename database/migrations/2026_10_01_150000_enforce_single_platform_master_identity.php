<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role') || ! Schema::hasColumn('users', 'email') || ! Schema::hasColumn('users', 'company_id')) {
            return;
        }

        $unexpectedMasterExists = DB::table('users')
            ->where('role', 'master')
            ->whereRaw('LOWER(email) <> ?', [User::PLATFORM_MASTER_EMAIL])
            ->exists();

        if ($unexpectedMasterExists) {
            throw new RuntimeException('Há usuários com papel Master fora da conta reservada. Corrija esses acessos antes de aplicar esta migration.');
        }

        $platformMasterExists = DB::table('users')
            ->whereRaw('LOWER(email) = ?', [User::PLATFORM_MASTER_EMAIL])
            ->exists();

        if ($platformMasterExists && ! DB::table('companies')->where('id', User::PLATFORM_MASTER_COMPANY_ID)->exists()) {
            throw new RuntimeException('A empresa vinculada à conta Master não foi encontrada. Crie ou restaure a empresa de ID '.User::PLATFORM_MASTER_COMPANY_ID.' antes de aplicar esta migration.');
        }

        DB::table('users')
            ->whereRaw('LOWER(email) = ?', [User::PLATFORM_MASTER_EMAIL])
            ->update(['role' => 'master', 'company_id' => User::PLATFORM_MASTER_COMPANY_ID]);
    }

    public function down(): void
    {
        // The platform master identity is an access-control invariant and is not reverted.
    }
};
