<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'cnpj')) {
                $table->string('cnpj', 14)->nullable();
            }
            if (! Schema::hasColumn('companies', 'domain')) {
                $table->string('domain', 190)->nullable()->unique();
            }
            if (! Schema::hasColumn('companies', 'zip_code')) {
                $table->string('zip_code', 20)->nullable();
            }
            if (! Schema::hasColumn('companies', 'street')) {
                $table->string('street', 190)->nullable();
            }
            if (! Schema::hasColumn('companies', 'number')) {
                $table->string('number', 30)->nullable();
            }
            if (! Schema::hasColumn('companies', 'complement')) {
                $table->string('complement', 120)->nullable();
            }
            if (! Schema::hasColumn('companies', 'neighborhood')) {
                $table->string('neighborhood', 120)->nullable();
            }
            if (! Schema::hasColumn('companies', 'city')) {
                $table->string('city', 120)->nullable();
            }
            if (! Schema::hasColumn('companies', 'state')) {
                $table->string('state', 2)->nullable();
            }
            if (! Schema::hasColumn('companies', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (! Schema::hasColumn('companies', 'contact_name')) {
                $table->string('contact_name', 120)->nullable();
            }
            if (! Schema::hasColumn('companies', 'contact_email')) {
                $table->string('contact_email', 190)->nullable();
            }
            if (! Schema::hasColumn('companies', 'contact_whatsapp')) {
                $table->string('contact_whatsapp', 30)->nullable();
            }
            if (! Schema::hasColumn('companies', 'admin_recovery_email')) {
                $table->string('admin_recovery_email', 190)->nullable();
            }
            if (! Schema::hasColumn('companies', 'secondary_recovery_email')) {
                $table->string('secondary_recovery_email', 190)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep company profile values when rolling back application code.
    }
};
