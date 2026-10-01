<?php

namespace App\Http\Controllers;

use App\Models\GanttTask;
use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class GanttTaskController extends Controller
{
    public function index(int $project): Response
    {
        $project = Project::query()->findOrFail($project);

        return Inertia::render('Projects/Timeline', [
            'project' => $project->only('id', 'name', 'code', 'status', 'start_date', 'deadline'),
            'tasks' => GanttTask::query()->where('project_id', $project->id)->with(['assignments' => fn ($assignments) => $assignments->where('company_id', $project->company_id)->whereHas('user', fn ($users) => $users->where('company_id', $project->company_id))->with('user:id,name')])->orderBy('sort_order')->get([
                'id', 'project_id', 'code', 'name', 'description', 'level', 'status', 'progress', 'start_at', 'end_at', 'duration', 'depends', 'sort_order', 'start_is_milestone', 'end_is_milestone',
            ]),
        ]);
    }
}
