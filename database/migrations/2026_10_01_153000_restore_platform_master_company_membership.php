<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $platformMasterExists = DB::table('users')
            ->whereRaw('LOWER(email) = ?', [User::PLATFORM_MASTER_EMAIL])
            ->exists();

        if ($platformMasterExists && ! DB::table('companies')->where('id', User::PLATFORM_MASTER_COMPANY_ID)->exists()) {
            throw new RuntimeException('A empresa vinculada à conta Master não foi encontrada. Crie ou restaure a empresa de ID '.User::PLATFORM_MASTER_COMPANY_ID.' antes de aplicar esta migration.');
        }

        if ($platformMasterExists) {
            DB::table('users')
                ->whereRaw('LOWER(email) = ?', [User::PLATFORM_MASTER_EMAIL])
                ->update([
                    'role' => 'master',
                    'company_id' => User::PLATFORM_MASTER_COMPANY_ID,
                ]);
        }
    }

    public function down(): void
    {
        // Keep the platform account linked to its company when rolling back.
    }
};
