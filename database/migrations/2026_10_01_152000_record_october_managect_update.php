<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('release_versions')) {
            return;
        }

        DB::table('release_versions')->updateOrInsert(
            [
                'branch_name' => 'main',
                'commit_sha' => '5fb1009a595c1dabe9bc71e6b33213ca5ac8622c',
            ],
            [
                'commit_message' => 'Projetos, Gantt e visão administrativa integrados',
                'implemented_notes' => json_encode([
                    'Backlog conectado ao Gantt, com vários itens de trabalho associados a tarefas finais.',
                    'Visão geral do projeto reúne backlog, progresso, atrasos e próximas entregas.',
                    'Projetos e tarefas do Gantt integrados ao painel multiempresa.',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'fixed_notes' => json_encode([
                    'Navegação do Gantt preserva o projeto selecionado.',
                    'Foto do perfil exibida corretamente após o envio.',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_notes' => json_encode([
                    'Painel administrativo e perfil alinhados à experiência em português.',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'executed_by' => 'Samuel Ferreira de Melo',
                'released_at' => '2026-10-01 15:37:45',
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // Preserve published history even when rolling back application code.
    }
};
