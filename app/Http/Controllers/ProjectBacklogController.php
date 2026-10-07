<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectBacklog;
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
        $project = Project::query()->findOrFail($project);
        $backlogs = $project->backlogs()->withCount(['items', 'tasks'])->get(['id', 'project_id', 'code', 'name', 'description', 'status', 'created_at']);

        return Inertia::render('Projects/Backlogs', [
            'project' => $project->only('id', 'name', 'code', 'description', 'status'),
            'backlogs' => $backlogs,
            'canManage' => $project->canBeManagedBy(request()->user()),
        ]);
    }

    public function store(Request $request, int $project): RedirectResponse
    {
        $project = Project::query()->findOrFail($project);
        $this->authorizeProjectManagement($project);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('project_backlogs')->where('company_id', $project->company_id)->where('project_id', $project->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['code'] = filled($data['code'] ?? null) ? mb_strtoupper(trim($data['code'])) : $this->nextBacklogCode($project);

        $project->backlogs()->create($data + ['company_id' => $project->company_id]);

        return back()->with('success', 'Backlog criado dentro do projeto.');
    }

    private function nextBacklogCode(Project $project): string
    {
        $number = $project->backlogs()->count() + 1;

        do {
            $code = sprintf('BL-%02d', $number++);
        } while ($project->backlogs()->where('code', $code)->exists());

        return $code;
    }

    public function show(int $project, int $backlog): Response
    {
        $project = Project::query()->findOrFail($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->withCount(['items', 'tasks'])->findOrFail($backlog);

        return Inertia::render('Projects/Backlog', [
            'project' => $project->only('id', 'name', 'code', 'description', 'status'),
            'backlog' => $backlog->only('id', 'code', 'name', 'description', 'status', 'items_count', 'tasks_count'),
            'canManage' => $project->canBeManagedBy(request()->user()),
            'items' => $backlog->items()->with(['ganttTasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])->orderBy('priority')->orderBy('code')->get(['id', 'project_id', 'project_backlog_id', 'code', 'epic', 'title', 'description', 'priority', 'status', 'release', 'points']),
        ]);
    }

    public function storeItem(Request $request, int $project, int $backlog): RedirectResponse
    {
        $project = Project::query()->findOrFail($project);
        $this->authorizeProjectManagement($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->findOrFail($backlog);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('project_backlog_items')->where('company_id', $project->company_id)->where('project_id', $project->id)],
            'epic' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(['P0', 'P1', 'P2', 'P3'])],
            'release' => ['nullable', 'string', 'max:12'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $backlog->items()->create($data + ['company_id' => $project->company_id, 'project_id' => $project->id]);

        return back()->with('success', 'Item incluído no backlog.');
    }

    public function updateStatus(Request $request, int $project, int $backlog, int $item): RedirectResponse
    {
        $project = Project::query()->findOrFail($project);
        $this->authorizeProjectManagement($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->findOrFail($backlog);
        $item = ProjectBacklogItem::query()->where('project_backlog_id', $backlog->id)->findOrFail($item);

        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'in_progress', 'validation', 'done'])]]);
        $item->update($data);

        return back()->with('success', 'Status atualizado.');
    }

    public function syncGanttTasks(Request $request, int $project, int $backlog, int $item): RedirectResponse
    {
        $project = Project::query()->findOrFail($project);
        $this->authorizeProjectManagement($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->findOrFail($backlog);
        $item = ProjectBacklogItem::query()->where('project_backlog_id', $backlog->id)->findOrFail($item);
        $data = $request->validate(['task_ids' => ['present', 'array', 'max:500'], 'task_ids.*' => ['required', 'integer', 'distinct']]);
        $taskIds = array_map('intval', $data['task_ids']);
        $projectTasks = $backlog->tasks()->orderBy('sort_order')->orderBy('id')->get(['id', 'level', 'project_backlog_item_id']);
        $tasks = $projectTasks->whereIn('id', $taskIds);
        if ($tasks->count() !== count($taskIds)) {
            throw ValidationException::withMessages(['task_ids' => 'Selecione apenas tarefas do Gantt deste backlog.']);
        }

        $leafTaskIds = $projectTasks->filter(function (GanttTask $task, int $index) use ($projectTasks): bool {
            return ! isset($projectTasks[$index + 1]) || (int) $projectTasks[$index + 1]->level <= (int) $task->level;
        })->pluck('id')->map(fn ($id) => (int) $id);
        if (collect($taskIds)->diff($leafTaskIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['task_ids' => 'Vincule apenas tarefas finais do Gantt para evitar duplicidade.']);
        }

        DB::transaction(function () use ($request, $project, $backlog, $item, $taskIds): void {
            $lockedTasks = $backlog->tasks()->whereIn('id', $taskIds)->lockForUpdate()->get(['id', 'project_backlog_item_id']);
            if ($lockedTasks->count() !== count($taskIds)) {
                throw ValidationException::withMessages(['task_ids' => 'Uma das tarefas foi removida. Atualize a página e tente novamente.']);
            }
            if ($lockedTasks->contains(fn (GanttTask $task) => $task->project_backlog_item_id !== null && (int) $task->project_backlog_item_id !== (int) $item->id)) {
                throw ValidationException::withMessages(['task_ids' => 'Uma das tarefas já está vinculada a outro item do backlog.']);
            }

            $toDetach = $backlog->tasks()->where('project_backlog_item_id', $item->id);
            if ($taskIds !== []) {
                $toDetach->whereNotIn('id', $taskIds);
            }
            $toDetach->update(['project_backlog_item_id' => null, 'updated_by' => $request->user()->id]);
            if ($taskIds !== []) {
                $backlog->tasks()->whereIn('id', $taskIds)->update(['project_backlog_item_id' => $item->id, 'updated_by' => $request->user()->id]);
            }
            // Quem estiver com o Gantt aberto precisa recarregar antes de salvar (os vínculos mudaram).
            $backlog->increment('gantt_revision');

            AuditLog::query()->create([
                'user_id' => $request->user()->id, 'company_id' => $project->company_id,
                'action' => 'backlog.gantt_tasks_synced', 'entity_type' => 'project_backlog_items', 'entity_id' => $item->id,
                'description' => 'Vínculos de tarefas do Gantt atualizados para o item '.$item->code,
                'route_name' => $request->route()?->getName(), 'method' => $request->method(),
                'path' => '/'.$request->route()?->uri(), 'status_code' => 200, 'outcome' => 'success',
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'metadata' => ['task_ids' => $taskIds], 'created_at' => now(),
            ]);
        });

        return back()->with('success', 'Vínculos com o Gantt atualizados.');
    }
}
