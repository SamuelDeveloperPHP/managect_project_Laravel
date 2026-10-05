<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class ProjectOverviewController extends Controller
{
    public function show(int $project): Response
    {
        $project = Project::query()->findOrFail($project);
        $backlogs = $project->backlogs()
            ->withCount(['items', 'tasks'])
            ->get(['id', 'project_id', 'code', 'name', 'description', 'status']);

        return Inertia::render('Projects/Overview', [
            'project' => $project->only('id', 'name', 'code', 'status', 'client', 'start_date', 'deadline'),
            'backlogs' => $backlogs,
        ]);
    }
}
