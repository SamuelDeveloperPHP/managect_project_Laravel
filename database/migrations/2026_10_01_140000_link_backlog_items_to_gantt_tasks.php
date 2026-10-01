<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gantt_tasks') || Schema::hasColumn('gantt_tasks', 'project_backlog_item_id')) {
            return;
        }

        Schema::table('gantt_tasks', function (Blueprint $table): void {
            $table->foreignId('project_backlog_item_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_backlog_items')
                ->nullOnDelete();
            $table->index(['company_id', 'project_backlog_item_id'], 'gantt_tasks_backlog_lookup');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gantt_tasks') || ! Schema::hasColumn('gantt_tasks', 'project_backlog_item_id')) {
            return;
        }

        Schema::table('gantt_tasks', function (Blueprint $table): void {
            $table->dropForeign(['project_backlog_item_id']);
            $table->dropIndex('gantt_tasks_backlog_lookup');
            $table->dropColumn('project_backlog_item_id');
        });
    }
};
