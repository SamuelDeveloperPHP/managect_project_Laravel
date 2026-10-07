<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The baseline migration can inherit this shared release table from the
        // Phalcon schema, so keep existing release history intact.
        if (Schema::hasTable('release_versions')) {
            return;
        }

        Schema::create('release_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('branch_name', 190);
            $table->char('commit_sha', 40);
            $table->string('commit_message', 500)->nullable();
            $table->json('implemented_notes')->nullable();
            $table->json('fixed_notes')->nullable();
            $table->json('updated_notes')->nullable();
            $table->string('executed_by', 190);
            $table->dateTime('released_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['branch_name', 'commit_sha'], 'release_versions_branch_commit_unique');
            $table->index(['released_at', 'id'], 'release_versions_released_index');
        });
    }

    public function down(): void
    {
        // This table may predate the Laravel migration history; do not remove
        // its imported records during rollback.
    }
};
