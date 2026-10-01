<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This database was created from the Phalcon schema/data; attach source IDs
        // to the existing rows instead of inserting a second copy of them.
        if (Schema::hasTable('gantt_tasks') && Schema::hasColumn('gantt_tasks', 'phalcon_id')) {
            DB::table('gantt_tasks')->whereNull('phalcon_id')->update(['phalcon_id' => DB::raw('id')]);
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'phalcon_id')) {
            DB::table('projects')->whereNull('phalcon_id')
                ->whereIn('id', DB::table('gantt_tasks')->select('project_id')->whereNotNull('project_id'))
                ->update(['phalcon_id' => DB::raw('id')]);
        }

        if (Schema::hasTable('gantt_task_assignments') && Schema::hasColumn('gantt_task_assignments', 'phalcon_id')) {
            DB::table('gantt_task_assignments')->whereNull('phalcon_id')->update(['phalcon_id' => DB::raw('id')]);
        }

        if (Schema::hasTable('project_members') && Schema::hasColumn('project_members', 'phalcon_project_id')) {
            DB::table('project_members')->whereNull('phalcon_project_id')->update(['phalcon_project_id' => DB::raw('project_id')]);
        }
    }

    public function down(): void
    {
        // Keep import identity markers on rollback; removing them would make a
        // subsequent import less safe and does not affect application behavior.
    }
};
