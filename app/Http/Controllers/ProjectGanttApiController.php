<?php

namespace App\Http\Controllers;

use App\Exceptions\GanttRevisionConflict;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectBacklogItem;
use App\Models\ProjectBacklog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProjectGanttApiController extends Controller
{
    private const STATUSES = ['STATUS_ACTIVE', 'STATUS_DONE', 'STATUS_WAITING', 'STATUS_SUSPENDED', 'STATUS_FAILED', 'STATUS_UNDEFINED'];
    private const ROLES = ['responsible' => 'Responsável', 'supporter' => 'Apoiador', 'reviewer' => 'Revisor'];

    public function show(int $project, int $backlog): JsonResponse
    {
        $project = Project::query()->findOrFail($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->findOrFail($backlog);
        return response()->json(['success' => true, 'project' => $this->projectPayload($project, $backlog)]);
    }

    public function save(Request $request, int $project, int $backlog): JsonResponse
    {
        $project = Project::query()->findOrFail($project);
        $backlog = ProjectBacklog::query()->where('project_id', $project->id)->findOrFail($backlog);
        if (strlen($request->getContent()) > 1_048_576) {
            return response()->json(['success' => false, 'message' => 'O arquivo enviado excede o limite de 1 MB.'], 413);
        }

        $payload = $request->all();
        // O editor cria tarefas novas com ids temporários ("tmp_..."): para o servidor elas são tarefas sem id.
        if (isset($payload['tasks']) && is_array($payload['tasks'])) {
            foreach ($payload['tasks'] as $index => $task) {
                if (is_array($task) && array_key_exists('id', $task) && ! (is_int($task['id']) || (is_string($task['id']) && ctype_digit($task['id'])))) {
                    $payload['tasks'][$index]['id'] = null;
                }
                if (is_array($task) && isset($task['backlogItemId']) && $task['backlogItemId'] === '') {
                    $payload['tasks'][$index]['backlogItemId'] = null;
                }
            }
        }

        $validator = Validator::make($payload, [
            'revision' => ['required', 'integer', 'min:0'],
            'tasks' => ['present', 'array', 'max:500'],
            'tasks.*.id' => ['nullable', 'integer'],
            'tasks.*.name' => ['nullable', 'string', 'max:190'],
            'tasks.*.code' => ['nullable', 'string', 'max:80'],
            'tasks.*.description' => ['nullable', 'string', 'max:4000'],
            'tasks.*.level' => ['nullable', 'integer', 'between:0,20'],
            'tasks.*.status' => ['nullable', Rule::in(self::STATUSES)],
            'tasks.*.progress' => ['nullable', 'integer', 'between:0,100'],
            'tasks.*.start' => ['required', 'numeric', 'min:0'],
            'tasks.*.end' => ['required', 'numeric', 'min:0'],
            'tasks.*.duration' => ['nullable', 'integer', 'between:1,3650'],
            'tasks.*.depends' => ['nullable', 'string', 'max:255'],
            'tasks.*.backlogItemId' => ['nullable', 'integer'],
            'tasks.*.collapsed' => ['nullable', 'boolean'],
            'tasks.*.startIsMilestone' => ['nullable', 'boolean'],
            'tasks.*.endIsMilestone' => ['nullable', 'boolean'],
            'tasks.*.assigs' => ['nullable', 'array', 'max:20'],
            'tasks.*.assigs.*.resourceId' => ['required_with:tasks.*.assigs', 'integer'],
            'tasks.*.assigs.*.roleId' => ['required_with:tasks.*.assigs', 'string', Rule::in(array_keys(self::ROLES))],
            'tasks.*.assigs.*.effort' => ['nullable', 'integer', 'between:0,31536000000'],
        ], [
            'tasks.*.id.integer' => 'Identificador de tarefa inválido.',
            'tasks.*.level.*' => 'O nível da tarefa é inválido.',
            'tasks.*.progress.*' => 'O progresso deve ser um número inteiro entre 0 e 100.',
            'tasks.*.start.*' => 'A data inicial de uma tarefa é inválida.',
            'tasks.*.end.*' => 'A data final de uma tarefa é inválida.',
            'tasks.*.duration.*' => 'A duração deve ser de 1 a 3650 dias.',
            'tasks.*.name.max' => 'O nome da tarefa pode ter no máximo 190 caracteres.',
            'tasks.*.code.max' => 'O código da tarefa pode ter no máximo 80 caracteres.',
            'tasks.*.assigs.*' => 'Um responsável da tarefa é inválido.',
            'tasks.*.status.*' => 'O status da tarefa é inválido.',
            'tasks.*.depends.*' => 'A lista de predecessoras é inválida.',
            'tasks.*.backlogItemId.*' => 'O vínculo com o item do backlog é inválido.',
            'revision.*' => 'A página do cronograma está desatualizada. Recarregue-a e tente salvar de novo.',
            'tasks.max' => 'O cronograma aceita no máximo 500 tarefas.',
            'tasks.*' => 'Os dados do cronograma são inválidos.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $tasks = array_values($validator->validated()['tasks']);
        $companyId = (int) $project->company_id;
        $backlogItemIds = collect($tasks)->pluck('backlogItemId')->filter()->map(fn ($id) => (int) $id)->unique();
        $validBacklogItemIds = ProjectBacklogItem::query()->where('project_backlog_id', $backlog->id)->whereIn('id', $backlogItemIds)->pluck('id')->map(fn ($id) => (int) $id);
        if ($backlogItemIds->diff($validBacklogItemIds)->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Um item de backlog vinculado não pertence a este projeto.'], 422);
        }
        $existing = GanttTask::query()->where('project_backlog_id', $backlog->id)->get()->keyBy('id');
        $activeUsers = User::query()->where('company_id', $companyId)->where('is_active', true)
            ->whereIn('id', collect($tasks)->flatMap(fn (array $task) => collect($task['assigs'] ?? [])->pluck('resourceId'))->unique())
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        try {
            $prepared = $this->prepareTasks($tasks, $activeUsers);
            foreach ($prepared as $index => $task) {
                $taskId = (int) ($task['id'] ?? 0);
                $backlogItemId = array_key_exists('backlogItemId', $task)
                    ? $task['backlogItemId']
                    : $existing->get($taskId)?->project_backlog_item_id;
                $hasChildren = isset($prepared[$index + 1]) && (int) $prepared[$index + 1]['level'] > (int) $task['level'];
                if ($backlogItemId && $hasChildren) {
                    return response()->json(['success' => false, 'message' => 'Uma tarefa vinculada ao backlog não pode se tornar tarefa-pai. Ajuste o vínculo antes de alterar a hierarquia.'], 422);
                }
            }

            $expectedRevision = (int) $validator->validated()['revision'];

            DB::transaction(function () use ($prepared, $existing, $project, $backlog, $companyId, $request, $expectedRevision): void {
                // Trava a linha do backlog: duas gravações simultâneas passam uma de cada vez.
                $current = ProjectBacklog::query()->whereKey($backlog->id)->lockForUpdate()->firstOrFail();
                if ((int) $current->gantt_revision !== $expectedRevision) {
                    throw new GanttRevisionConflict((int) $current->gantt_revision);
                }

                $retainedIds = [];
                foreach ($prepared as $index => $task) {
                    $taskId = (int) ($task['id'] ?? 0);
                    if ($taskId > 0 && ! $existing->has($taskId)) {
                        throw new \RuntimeException('Uma tarefa enviada não pertence a este cronograma.');
                    }

                    $record = $taskId > 0 ? $existing->get($taskId) : new GanttTask();
                    $record->fill([
                        'project_id' => $project->id,
                        'project_backlog_id' => $backlog->id,
                        'company_id' => $companyId,
                        'code' => $task['code'] ?? null,
                        'name' => trim($task['name'] ?? '') ?: 'Nova tarefa '.($index + 1),
                        'description' => $task['description'] ?? null,
                        'level' => (int) ($task['level'] ?? 0),
                        'status' => $task['status'] ?? 'STATUS_ACTIVE',
                        'progress' => ($task['status'] ?? '') === 'STATUS_DONE' ? 100 : (int) ($task['progress'] ?? 0),
                        'start_at' => $task['start_at'],
                        'end_at' => $task['end_at'],
                        'duration' => max(1, (int) ($task['duration'] ?? 1)),
                        'depends' => $task['depends'] ?? '',
                        'sort_order' => $index,
                        'collapsed' => (bool) ($task['collapsed'] ?? false),
                        'start_is_milestone' => (bool) ($task['startIsMilestone'] ?? false),
                        'end_is_milestone' => (bool) ($task['endIsMilestone'] ?? false),
                        'updated_by' => $request->user()->id,
                    ]);
                    if (array_key_exists('backlogItemId', $task)) {
                        $record->project_backlog_item_id = $task['backlogItemId'] ? (int) $task['backlogItemId'] : null;
                    }
                    if (! $taskId) $record->created_by = $request->user()->id;
                    $record->save();
                    $retainedIds[] = $record->id;

                    $record->assignments()->delete();
                    foreach ($task['assigs'] ?? [] as $assignment) {
                        $record->assignments()->create([
                            'company_id' => $companyId,
                            'user_id' => (int) $assignment['resourceId'],
                            'role' => $assignment['roleId'],
                            'effort' => max(0, min(31_536_000_000, (int) ($assignment['effort'] ?? 0))),
                        ]);
                    }
                }

                GanttTask::query()->where('project_backlog_id', $backlog->id)->whereNotIn('id', $retainedIds)->delete();
                $current->increment('gantt_revision');
            });
            $backlog->refresh();
        } catch (GanttRevisionConflict $conflict) {
            return response()->json(['success' => false, 'conflict' => true, 'revision' => $conflict->currentRevision, 'message' => $conflict->getMessage()], 409);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível salvar o cronograma.'], 422);
        }

        return response()->json([
            'success' => true,
            'project' => $this->projectPayload($project, $backlog),
            'message' => 'Cronograma salvo com sucesso.',
        ]);
    }

    private function prepareTasks(array $tasks, array $activeUsers): array
    {
        $prepared = [];
        foreach ($tasks as $index => $task) {
            $start = CarbonImmutable::createFromTimestampMs((int) $task['start'])->setTimezone(config('app.timezone'));
            $end = CarbonImmutable::createFromTimestampMs((int) $task['end'])->setTimezone(config('app.timezone'));
            if ($end->lt($start)) throw new \RuntimeException('A data final não pode ser anterior à data inicial.');

            $depends = trim((string) ($task['depends'] ?? ''));
            foreach (array_filter(explode(',', $depends)) as $dependency) {
                if (! preg_match('/^([1-9][0-9]*)(?::(-?[0-9]+))?$/', trim($dependency), $matches)
                    || (int) $matches[1] > count($tasks) || (int) $matches[1] === $index + 1) {
                    throw new \RuntimeException('Uma predecessora aponta para uma tarefa inválida.');
                }
            }

            foreach ($task['assigs'] ?? [] as $assignment) {
                if (! in_array((int) $assignment['resourceId'], $activeUsers, true)) {
                    throw new \RuntimeException('Um responsável selecionado não pertence à empresa ou está inativo.');
                }
            }
            $prepared[] = $task + ['start_at' => $start, 'end_at' => $end];
            $prepared[$index]['start_at'] = $start;
            $prepared[$index]['end_at'] = $end;
        }

        $this->assertNoDependencyCycle($prepared);

        return $prepared;
    }

    /** Predecessoras em ciclo travariam o cronograma ao reabrir; recusamos antes de gravar. */
    private function assertNoDependencyCycle(array $tasks): void
    {
        $graph = [];
        foreach ($tasks as $index => $task) {
            $graph[$index + 1] = [];
            foreach (array_filter(explode(',', trim((string) ($task['depends'] ?? '')))) as $dependency) {
                if (preg_match('/^([1-9][0-9]*)/', trim($dependency), $matches)) {
                    $graph[$index + 1][] = (int) $matches[1];
                }
            }
        }

        $state = [];
        $visit = function (int $node) use (&$visit, &$state, $graph): bool {
            if (($state[$node] ?? 0) === 1) {
                return true;
            }
            if (($state[$node] ?? 0) === 2) {
                return false;
            }
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $state[$node] = 2;

            return false;
        };

        foreach (array_keys($graph) as $node) {
            if ($visit($node)) {
                throw new \RuntimeException('As predecessoras formam um ciclo (uma tarefa depende dela mesma). Remova o vínculo circular e salve de novo.');
            }
        }
    }

    private function projectPayload(Project $project, ProjectBacklog $backlog): array
    {
        $canWrite = request()->user()->hasPermission('can_manage_projects');
        $tasks = GanttTask::query()->where('project_backlog_id', $backlog->id)
            ->with(['assignments' => fn ($query) => $query->where('company_id', $project->company_id)->whereHas('user', fn ($users) => $users->where('company_id', $project->company_id))])
            ->orderBy('sort_order')->orderBy('id')->get();
        $items = $tasks->map(fn (GanttTask $task) => [
            'id' => (int) $task->id,
            'name' => $task->name,
            'progress' => (int) $task->progress,
            'progressByWorklog' => false,
            'relevance' => 0,
            'type' => '',
            'typeId' => '',
            'description' => $task->description ?? '',
            'code' => $task->code ?? '',
            'backlogItemId' => $task->project_backlog_item_id ? (int) $task->project_backlog_item_id : null,
            'level' => (int) $task->level,
            'status' => $task->status,
            'color' => '#3aaf85',
            'depends' => $task->depends,
            'canWrite' => $canWrite,
            'canAdd' => $canWrite,
            'canDelete' => $canWrite,
            'start' => $task->start_at->getTimestamp() * 1000,
            'duration' => (int) $task->duration,
            'end' => $task->end_at->getTimestamp() * 1000 + 999,
            'startIsMilestone' => (bool) $task->start_is_milestone,
            'endIsMilestone' => (bool) $task->end_is_milestone,
            'collapsed' => (bool) $task->collapsed,
            'assigs' => $task->assignments->map(fn ($assignment) => [
                'id' => (string) $assignment->id,
                'resourceId' => (string) $assignment->user_id,
                'roleId' => $assignment->role,
                'effort' => (int) $assignment->effort,
            ])->values()->all(),
            'hasChild' => false,
        ])->values()->all();

        if ($items === []) {
            $start = now()->startOfDay();
            $items = [[
                'id' => -1, 'name' => 'Projeto inicial', 'progress' => 0, 'progressByWorklog' => false, 'relevance' => 0,
                'type' => '', 'typeId' => '', 'description' => 'Primeira tarefa do cronograma.', 'code' => $project->code,
                'level' => 0, 'status' => 'STATUS_ACTIVE', 'color' => '#3aaf85', 'depends' => '', 'canWrite' => $canWrite,
                'canAdd' => $canWrite, 'canDelete' => $canWrite, 'start' => $start->getTimestamp() * 1000, 'duration' => 5,
                'end' => $start->copy()->addDays(4)->endOfDay()->getTimestamp() * 1000 + 999,
                'startIsMilestone' => false, 'endIsMilestone' => false, 'collapsed' => false, 'assigs' => [], 'hasChild' => false,
            ]];
        }

        return [
            'revision' => (int) $backlog->gantt_revision,
            'tasks' => $items,
            'selectedRow' => 0,
            'deletedTaskIds' => [],
            'resources' => User::query()->where('company_id', $project->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn (User $user) => ['id' => (string) $user->id, 'name' => $user->name])->all(),
            'roles' => collect(self::ROLES)->map(fn (string $name, string $id) => ['id' => $id, 'name' => $name])->values()->all(),
            'canWrite' => $canWrite,
            'canAdd' => $canWrite,
            'canWriteOnParent' => $canWrite,
            'canDelete' => $canWrite,
            'canSeeCriticalPath' => true,
            'canAddIssue' => false,
            'cannotCloseTaskIfIssueOpen' => false,
            'zoom' => 'w3',
        ];
    }
}
