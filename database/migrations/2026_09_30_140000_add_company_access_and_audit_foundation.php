<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            if (Schema::hasColumn('companies', 'cnpj')) {
                $normalizedDocuments = DB::table('companies')->whereNotNull('cnpj')->pluck('cnpj')
                    ->map(fn ($document): string => preg_replace('/\D+/', '', (string) $document) ?? '')
                    ->filter();

                if ($normalizedDocuments->count() !== $normalizedDocuments->unique()->count()) {
                    throw new RuntimeException('Há CNPJ duplicado após normalização. Corrija os dados antes da migration de acesso.');
                }
            }

            Schema::table('companies', function (Blueprint $table): void {
                if (! Schema::hasColumn('companies', 'document_type')) {
                    $table->string('document_type', 4)->nullable()->after('name');
                }
                if (! Schema::hasColumn('companies', 'document_number')) {
                    $table->string('document_number', 14)->nullable()->after('document_type');
                }
                if (! Schema::hasColumn('companies', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('slug');
                }
                if (! Schema::hasColumn('companies', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable();
                }
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                $documentTypeColumn = DB::selectOne("SHOW COLUMNS FROM companies LIKE 'document_type'");
                $documentTypeLength = preg_match('/varchar\((\d+)\)/i', (string) ($documentTypeColumn->Type ?? ''), $match)
                    ? (int) $match[1]
                    : 0;

                if ($documentTypeLength > 0 && $documentTypeLength < 4) {
                    DB::statement('ALTER TABLE companies MODIFY document_type VARCHAR(4) NULL');
                }
            }

            if (Schema::hasColumn('companies', 'cnpj')) {
                DB::table('companies')->whereNotNull('cnpj')->orderBy('id')->each(function (object $company): void {
                    $document = preg_replace('/\D+/', '', (string) $company->cnpj);
                    if ($document !== '') {
                        DB::table('companies')->where('id', $company->id)->whereNull('document_number')->update([
                            'document_type' => 'CNPJ',
                            'document_number' => $document,
                        ]);
                    }
                });
            }

            if (! Schema::hasIndex('companies', 'companies_document_unique')) {
                $duplicates = DB::table('companies')
                    ->select('document_type', 'document_number')
                    ->whereNotNull('document_number')
                    ->groupBy('document_type', 'document_number')
                    ->havingRaw('COUNT(*) > 1')
                    ->exists();

                if ($duplicates) {
                    throw new RuntimeException('Há CNPJ/CPF duplicado em companies. Corrija os vínculos antes de aplicar a migration de acesso.');
                }

                Schema::table('companies', function (Blueprint $table): void {
                    $table->unique(['document_type', 'document_number'], 'companies_document_unique');
                });
            }
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'role')) {
                    $table->string('role', 20)->default('user');
                }
                if (! Schema::hasColumn('users', 'permissions')) {
                    $table->json('permissions')->nullable();
                }
                if (! Schema::hasColumn('users', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('users', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable();
                }
                if (! Schema::hasColumn('users', 'cpf')) {
                    $table->string('cpf', 11)->nullable();
                }
                if (! Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable();
                }
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                $roleColumn = DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'");
                $roleType = strtolower((string) ($roleColumn->Type ?? ''));

                if (str_starts_with($roleType, 'enum(') && ! str_contains($roleType, "'master'")) {
                    DB::statement("ALTER TABLE users MODIFY role ENUM('master','admin','user') NOT NULL DEFAULT 'user'");
                }
            }
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 120);
                $table->string('entity_type', 80)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('description')->nullable();
                $table->string('route_name')->nullable();
                $table->string('method', 10);
                $table->text('path');
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->string('outcome', 20)->default('unknown');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['company_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        } else {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'route_name')) {
                    $table->string('route_name')->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'method')) {
                    $table->string('method', 10)->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'path')) {
                    $table->text('path')->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'status_code')) {
                    $table->unsignedSmallInteger('status_code')->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'outcome')) {
                    $table->string('outcome', 20)->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'entity_type')) {
                    $table->string('entity_type', 80)->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'entity_id')) {
                    $table->unsignedBigInteger('entity_id')->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('audit_logs', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                $companyColumn = DB::selectOne("SHOW COLUMNS FROM audit_logs LIKE 'company_id'");
                if ($companyColumn !== null && strtoupper((string) $companyColumn->Null) !== 'YES') {
                    DB::statement('ALTER TABLE audit_logs MODIFY company_id BIGINT UNSIGNED NULL');
                }
            }
        }
    }

    public function down(): void
    {
        // Preserve audit history and legacy data when rolling back application code.
    }
};
