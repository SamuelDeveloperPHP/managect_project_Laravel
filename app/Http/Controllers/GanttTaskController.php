<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBacklog;
use Inertia\Inertia;
use Inertia\Response;

class GanttTaskController extends Controller
{
    public function index(int $project, int $backlog): Response
    {
        $project = Project::query()->findOrFail($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->findOrFail($backlog);

        return Inertia::render('Projects/Timeline', [
            'project' => $project->only('id', 'name', 'code', 'status', 'start_date', 'deadline'),
            'backlog' => $backlog->only('id', 'name', 'code'),
            'canManage' => request()->user()->hasPermission('can_manage_projects'),
            'ganttTemplates' => $this->templates(),
        ]);
    }

    private function templates(): string
    {
        $html = @file_get_contents(resource_path('assets/gantt-templates.html'));
        if (! is_string($html)) return '<div id="gantEditorTemplates" style="display:none"></div>';

        $start = strpos($html, '<div id="gantEditorTemplates"');
        $script = $start === false ? false : strpos($html, '<script type="text/javascript">', $start);
        $beforeScript = $start === false || $script === false ? '' : substr($html, $start, $script - $start);
        $end = strrpos($beforeScript, '</div>');
        if ($start === false || $script === false || $end === false) return '<div id="gantEditorTemplates" style="display:none"></div>';

        $templates = substr($beforeScript, 0, $end + strlen('</div>'));
        $translations = [
            'title="undo"' => 'title="Desfazer" aria-label="Desfazer"',
            'title="redo"' => 'title="Refazer" aria-label="Refazer"',
            'title="insert above"' => 'title="Inserir tarefa acima" aria-label="Inserir tarefa acima"',
            'title="insert below"' => 'title="Inserir tarefa abaixo" aria-label="Inserir tarefa abaixo"',
            'title="un-indent task"' => 'title="Recuar tarefa" aria-label="Recuar tarefa"',
            'title="indent task"' => 'title="Avançar tarefa" aria-label="Avançar tarefa"',
            'title="move up"' => 'title="Mover para cima" aria-label="Mover para cima"',
            'title="move down"' => 'title="Mover para baixo" aria-label="Mover para baixo"',
            'title="Elimina"' => 'title="Excluir" aria-label="Excluir"',
            'title="EXPAND_ALL"' => 'title="Expandir todas" aria-label="Expandir todas"',
            'title="COLLAPSE_ALL"' => 'title="Recolher todas" aria-label="Recolher todas"',
            'title="zoom out"' => 'title="Reduzir escala" aria-label="Reduzir escala"',
            'title="zoom in"' => 'title="Ampliar escala" aria-label="Ampliar escala"',
            'title="Print"' => 'title="Imprimir" aria-label="Imprimir"',
            'title="CRITICAL_PATH"' => 'title="Caminho crítico" aria-label="Caminho crítico"',
            'title="FULLSCREEN"' => 'title="Tela cheia" aria-label="Tela cheia"',
            'title="edit resources"' => 'title="Editar responsáveis" aria-label="Editar responsáveis"',
            'title="Save">Save</button>' => 'title="Salvar">Salvar</button>',
            '>Load</label>' => '>Importar</label>',
            '<em>clear project</em>' => '<em>Limpar cronograma</em>',
            '>code/short name</th>' => '>Código</th>',
            '>name</th>' => '>Tarefa</th>',
            '>start</th>' => '>Início</th>',
            '>End</th>' => '>Fim</th>',
            '>dur.</th>' => '>Dur.</th>',
            '>depe.</th>' => '>Dep.</th>',
            '>assignees</th>' => '>Responsáveis</th>',
            'title="Start date is a milestone."' => 'title="Data de início é um marco."',
            'title="End date is a milestone."' => 'title="Data de término é um marco."',
            'placeholder="code/short name"' => 'placeholder="Código"',
            'placeholder="name"' => 'placeholder="Tarefa"',
            'title="Active"' => 'title="Ativa"',
            'title="Completed"' => 'title="Concluída"',
            'title="Failed"' => 'title="Com impedimento"',
            'title="Suspended"' => 'title="Suspensa"',
            'title="Waiting"' => 'title="Aguardando"',
            'title="Undefined"' => 'title="Não definida"',
            '>Task editor</h2>' => '>Editar tarefa</h2>',
            '<label for="code">code/short name</label>' => '<label for="code">Código</label>',
            '<label for="name" class="required">name</label>' => '<label for="name" class="required">Tarefa</label>',
            '<label for="start">start</label>' => '<label for="start">Início</label>',
            '<label for="end">End</label>' => '<label for="end">Fim</label>',
            '<label for="duration" class=" ">Days</label>' => '<label for="duration" class=" ">Dias</label>',
            '<label for="status" class=" ">status</label>' => '<label for="status" class=" ">Status</label>',
            '>active</option>' => '>Ativa</option>',
            '>suspended</option>' => '>Suspensa</option>',
            '>completed</option>' => '>Concluída</option>',
            '>failed</option>' => '>Com impedimento</option>',
            '>undefined</option>' => '>Não definida</option>',
            '<label>progress</label>' => '<label>Progresso</label>',
            '<label for="description">Description</label>' => '<label for="description">Descrição</label>',
            '<h2>Assignments</h2>' => '<h2>Responsáveis</h2>',
            '>Role</th>' => '>Função</th>',
            '>est.wklg.</th>' => '>Trab. Est.</th>',
            '<h2>Project team</h2>' => '<h2>Equipe do projeto</h2>',
        ];

        $templates = str_replace('src="res/', 'src="/assets/jquery-gantt/res/', strtr($templates, $translations));
        return preg_replace("/\\s+on[a-z]+\\s*=\\s*(?:\"[^\"]*\"|'[^']*')/i", '', $templates) ?? $templates;
    }
}
