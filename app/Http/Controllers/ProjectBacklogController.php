<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectBacklogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectBacklogController extends Controller
{
    public function index(int $project): Response
    {
        $project = Project::findOrFail($project);

        return Inertia::render('Projects/Backlog', [
            'project' => $project->only('id', 'name', 'code', 'description', 'status'),
            'items' => $project->backlogItems()->orderBy('priority')->orderBy('code')->get(['id', 'code', 'epic', 'title', 'description', 'priority', 'status', 'release', 'points']),
        ]);
    }

    public function store(Request $request, int $project): RedirectResponse
    {
        $project = Project::findOrFail($project);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('project_backlog_items')->where('company_id', $project->company_id)->where('project_id', $project->id)],
            'epic' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(['P0', 'P1', 'P2', 'P3'])],
            'release' => ['nullable', 'string', 'max:12'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $project->backlogItems()->create($data);

        return back()->with('success', 'Item incluído no backlog.');
    }

    public function updateStatus(Request $request, int $project, int $item): RedirectResponse
    {
        $project = Project::findOrFail($project);
        $item = ProjectBacklogItem::findOrFail($item);

        abort_unless($item->project_id === $project->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'in_progress', 'validation', 'done'])]]);
        $item->update($data);

        return back()->with('success', 'Status atualizado.');
    }

    public function syncGanttTasks(Request $request, int $project, int $item): RedirectResponse
    {
        $project = Project::query()->findOrFail($project);
        $item = ProjectBacklogItem::query()->where('project_id', $project->id)->findOrFail($item);
        $data = $request->validate([
            'task_ids' => ['present', 'array', 'max:500'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $taskIds = array_map('intval', $data['task_ids']);
        $projectTasks = GanttTask::query()->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'level', 'project_backlog_item_id']);
        $tasks = $projectTasks->whereIn('id', $taskIds);

        if ($tasks->count() !== count($taskIds)) {
            throw ValidationException::withMessages(['task_ids' => 'Selecione apenas tarefas do Gantt deste projeto.']);
        }

        $leafTaskIds = $projectTasks->filter(function (GanttTask $task, int $index) use ($projectTasks): bool {
            return ! isset($projectTasks[$index + 1]) || (int) $projectTasks[$index + 1]->level <= (int) $task->level;
        })->pluck('id')->map(fn ($id) => (int) $id);
        if (collect($taskIds)->diff($leafTaskIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['task_ids' => 'Vincule apenas tarefas finais do Gantt para manter os indicadores sem duplicidade.']);
        }

        DB::transaction(function () use ($request, $project, $item, $taskIds): void {
            $lockedTasks = GanttTask::query()->where('project_id', $project->id)->whereIn('id', $taskIds)->lockForUpdate()->get(['id', 'project_backlog_item_id']);
            if ($lockedTasks->count() !== count($taskIds)) {
                throw ValidationException::withMessages(['task_ids' => 'Uma das tarefas selecionadas foi removida. Atualize a página e tente novamente.']);
            }
            if ($lockedTasks->contains(fn (GanttTask $task) => $task->project_backlog_item_id !== null && (int) $task->project_backlog_item_id !== (int) $item->id)) {
                throw ValidationException::withMessages(['task_ids' => 'Uma das tarefas já está vinculada a outro item do backlog.']);
            }

            $toDetach = GanttTask::query()
                ->where('project_id', $project->id)
                ->where('project_backlog_item_id', $item->id);
            if ($taskIds !== []) {
                $toDetach->whereNotIn('id', $taskIds);
            }
            $toDetach->update(['project_backlog_item_id' => null, 'updated_by' => $request->user()->id]);

            if ($taskIds !== []) {
                GanttTask::query()->where('project_id', $project->id)->whereIn('id', $taskIds)->update([
                    'project_backlog_item_id' => $item->id,
                    'updated_by' => $request->user()->id,
                ]);
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'company_id' => $project->company_id,
                'action' => 'backlog.gantt_tasks_synced',
                'entity_type' => 'project_backlog_items',
                'entity_id' => $item->id,
                'description' => 'Vínculos de tarefas do Gantt atualizados para o item '.$item->code,
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => '/'.$request->route()?->uri(),
                'status_code' => 200,
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'metadata' => ['task_ids' => $taskIds],
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Vínculos com o Gantt atualizados.');
    }
}
