<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'phalcon_id')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->unsignedBigInteger('phalcon_id')->nullable()->unique();
            });
        }

        if (! Schema::hasTable('gantt_tasks')) {
            Schema::create('gantt_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code', 80)->nullable();
                $table->string('name', 190);
                $table->text('description')->nullable();
                $table->unsignedInteger('level')->default(0);
                $table->string('status', 40)->default('STATUS_ACTIVE');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->dateTime('start_at');
                $table->dateTime('end_at');
                $table->unsignedInteger('duration')->default(1);
                $table->string('depends', 255)->default('');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('collapsed')->default(false);
                $table->boolean('start_is_milestone')->default(false);
                $table->boolean('end_is_milestone')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'project_id', 'sort_order']);
                $table->index(['company_id', 'start_at', 'end_at']);
                $table->index(['company_id', 'status']);
            });
        }
        if (! Schema::hasColumn('gantt_tasks', 'phalcon_id')) {
            Schema::table('gantt_tasks', fn (Blueprint $table) => $table->unsignedBigInteger('phalcon_id')->nullable()->unique());
        }

        if (! Schema::hasTable('project_members')) {
            Schema::create('project_members', function (Blueprint $table): void {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();
                $table->primary(['project_id', 'user_id']);
            });
        }
        if (! Schema::hasColumn('project_members', 'phalcon_project_id')) {
            Schema::table('project_members', fn (Blueprint $table) => $table->unsignedBigInteger('phalcon_project_id')->nullable());
        }

        if (! Schema::hasTable('gantt_task_assignments')) {
            Schema::create('gantt_task_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('gantt_task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 40)->default('responsible');
                $table->unsignedBigInteger('effort')->default(0);
                $table->timestamps();
                $table->unique(['gantt_task_id', 'user_id', 'role'], 'gantt_assignment_identity');
                $table->index(['company_id', 'user_id']);
            });
        }
        if (! Schema::hasColumn('gantt_task_assignments', 'phalcon_id')) {
            Schema::table('gantt_task_assignments', fn (Blueprint $table) => $table->unsignedBigInteger('phalcon_id')->nullable()->unique());
        }
    }

    public function down(): void
    {
        foreach (['gantt_task_assignments' => 'phalcon_id', 'project_members' => 'phalcon_project_id', 'gantt_tasks' => 'phalcon_id', 'projects' => 'phalcon_id'] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
            }
        }
    }
};
