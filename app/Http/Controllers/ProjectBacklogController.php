<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBacklogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
}
