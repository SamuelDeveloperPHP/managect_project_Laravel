<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('migrations') && ! Schema::hasColumn('migrations', 'batch')) {
            Schema::table('migrations', function (Blueprint $table) {
                $table->unsignedInteger('batch')->default(0)->after('migration');
            });
        }

        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'slug')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });

            DB::table('companies')->whereNull('slug')->orderBy('id')->each(function (object $company): void {
                DB::table('companies')->where('id', $company->id)->update(['slug' => 'legacy-'.$company->id]);
            });

            Schema::table('companies', function (Blueprint $table) {
                $table->unique('slug');
            });
        }
    }

    public function down(): void
    {
        // Dados herdados do Phalcon são preservados ao reverter esta migration.
    }
};
