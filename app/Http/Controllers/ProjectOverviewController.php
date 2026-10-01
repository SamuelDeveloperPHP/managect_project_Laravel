<?php

namespace App\Http\Controllers;

use App\Models\GanttTask;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ProjectOverviewController extends Controller
{
    public function show(int $project): Response
    {
        $project = Project::query()->findOrFail($project);
        $backlogItems = $project->backlogItems()->with(['ganttTasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])->orderBy('priority')->orderBy('code')->get([
            'id', 'code', 'epic', 'title', 'description', 'priority', 'status', 'release', 'points',
        ])->values();
        $tasks = GanttTask::query()->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get([
            'id', 'code', 'name', 'description', 'level', 'status', 'progress', 'start_at', 'end_at', 'project_backlog_item_id',
        ])->values();

        $leaves = $tasks->filter(function (GanttTask $task, int $index) use ($tasks): bool {
            return ! isset($tasks[$index + 1]) || (int) $tasks[$index + 1]->level <= (int) $task->level;
        })->values();
        $backlogItems = $backlogItems->map(fn ($item) => [
            ...$item->only('id', 'code', 'epic', 'title', 'description', 'priority', 'status', 'release', 'points'),
            'gantt_tasks' => $item->ganttTasks->map(fn (GanttTask $task) => [
                'id' => $task->id,
                'code' => $task->code,
                'name' => $task->name,
                'status' => $task->status,
                'progress' => (int) $task->progress,
            ])->values(),
        ])->values();
        $statusLabels = [
            'STATUS_DONE' => 'Concluídas',
            'STATUS_ACTIVE' => 'Em andamento',
            'STATUS_WAITING' => 'Aguardando',
            'STATUS_SUSPENDED' => 'Suspensas',
            'STATUS_FAILED' => 'Com impedimento',
            'STATUS_UNDEFINED' => 'Não definidas',
        ];
        $statusCounts = array_fill_keys(array_keys($statusLabels), 0);
        $today = CarbonImmutable::today();
        $nextWeek = $today->addDays(7)->endOfDay();
        $progressTotal = 0;
        $late = 0;
        $dueSoon = 0;

        foreach ($leaves as $task) {
            $statusCounts[$task->status] = ($statusCounts[$task->status] ?? 0) + 1;
            $progressTotal += max(0, min(100, (int) $task->progress));
            $end = CarbonImmutable::parse($task->end_at);
            if ((int) $task->progress < 100 && $end->lt($today)) $late++;
            if ((int) $task->progress < 100 && $end->betweenIncluded($today, $nextWeek)) $dueSoon++;
        }

        $phases = [];
        foreach ($tasks as $index => $task) {
            if ((int) $task->level !== 0) continue;
            $children = collect();
            for ($cursor = $index + 1; isset($tasks[$cursor]) && (int) $tasks[$cursor]->level > 0; $cursor++) {
                if ($leaves->contains('id', $tasks[$cursor]->id)) $children->push($tasks[$cursor]);
            }
            if ($children->isEmpty() && $leaves->contains('id', $task->id)) $children->push($task);
            $count = $children->count();
            $phases[] = [
                'code' => $task->code ?: '—',
                'name' => $task->name,
                'total' => $count,
                'done' => $children->filter(fn (GanttTask $child) => (int) $child->progress >= 100)->count(),
                'progress' => $count ? (int) round($children->avg(fn (GanttTask $child) => max(0, min(100, (int) $child->progress)))) : 0,
            ];
        }

        $upcoming = $leaves->filter(fn (GanttTask $task) => (int) $task->progress < 100 && CarbonImmutable::parse($task->end_at)->gte($today))
            ->sortBy('end_at')->take(8)->map(fn (GanttTask $task) => [
                'id' => $task->id, 'code' => $task->code, 'name' => $task->name, 'end_at' => CarbonImmutable::parse($task->end_at)->toDateString(),
            ])->values();

        return Inertia::render('Projects/Overview', [
            'project' => $project->only('id', 'name', 'code', 'status', 'client', 'start_date', 'deadline'),
            'canManage' => request()->user()->hasPermission('can_manage_projects'),
            'backlogItems' => $backlogItems,
            'ganttTasks' => $leaves->map(fn (GanttTask $task) => [
                'id' => $task->id,
                'code' => $task->code,
                'name' => $task->name,
                'backlog_item_id' => $task->project_backlog_item_id ? (int) $task->project_backlog_item_id : null,
            ])->values(),
            'summary' => [
                'total' => $leaves->count(),
                'completed' => $statusCounts['STATUS_DONE'],
                'progress' => $leaves->isEmpty() ? 0 : (int) round($progressTotal / $leaves->count()),
                'late' => $late,
                'due_soon' => $dueSoon,
                'status_counts' => $statusCounts,
                'status_labels' => $statusLabels,
                'phases' => $phases,
                'upcoming' => $upcoming,
                'starts_at' => $leaves->isEmpty() ? null : CarbonImmutable::parse($leaves->min('start_at'))->toDateString(),
                'ends_at' => $leaves->isEmpty() ? null : CarbonImmutable::parse($leaves->max('end_at'))->toDateString(),
            ],
        ]);
    }
}
