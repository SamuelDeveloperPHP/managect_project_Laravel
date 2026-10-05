<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_backlogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'project_id', 'code']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::table('project_backlog_items', function (Blueprint $table): void {
            $table->foreignId('project_backlog_id')->nullable()->after('project_id')->constrained('project_backlogs')->cascadeOnDelete();
        });
        Schema::table('gantt_tasks', function (Blueprint $table): void {
            $table->foreignId('project_backlog_id')->nullable()->after('project_id')->constrained('project_backlogs')->cascadeOnDelete();
            $table->index(['company_id', 'project_backlog_id'], 'gantt_tasks_project_backlog_lookup');
        });

        DB::table('projects')->orderBy('id')->chunkById(500, function ($projects): void {
            foreach ($projects as $project) {
                $backlogId = DB::table('project_backlogs')->insertGetId([
                    'company_id' => $project->company_id,
                    'project_id' => $project->id,
                    'code' => 'BACKLOG-GERAL',
                    'name' => 'Backlog principal',
                    'description' => 'Backlog inicial criado para preservar e organizar os dados existentes deste projeto.',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('project_backlog_items')->where('project_id', $project->id)->update(['project_backlog_id' => $backlogId]);
                DB::table('gantt_tasks')->where('project_id', $project->id)->update(['project_backlog_id' => $backlogId]);
            }
        });

    }

    public function down(): void
    {
        Schema::table('gantt_tasks', function (Blueprint $table): void {
            $table->dropIndex('gantt_tasks_project_backlog_lookup');
            $table->dropForeign(['project_backlog_id']);
            $table->dropColumn('project_backlog_id');
        });
        Schema::table('project_backlog_items', function (Blueprint $table): void {
            $table->dropForeign(['project_backlog_id']);
            $table->dropColumn('project_backlog_id');
        });
        Schema::dropIfExists('project_backlogs');
    }
};
