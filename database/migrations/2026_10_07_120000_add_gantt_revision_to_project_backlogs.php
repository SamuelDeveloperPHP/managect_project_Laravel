<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controle de concorrência otimista do cronograma: cada gravação do Gantt incrementa a revisão
     * e só é aceita se o editor partiu da revisão atual (evita que duas pessoas se sobrescrevam).
     */
    public function up(): void
    {
        Schema::table('project_backlogs', function (Blueprint $table): void {
            $table->unsignedInteger('gantt_revision')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_backlogs', function (Blueprint $table): void {
            $table->dropColumn('gantt_revision');
        });
    }
};
