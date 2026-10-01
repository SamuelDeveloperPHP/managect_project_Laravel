<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Projects/Index', [
            'projects' => Project::query()->withCount(['backlogItems', 'tasks'])->latest()->get(['id', 'name', 'code', 'description', 'status', 'client', 'priority', 'start_date', 'deadline', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('projects')->where('company_id', app(TenantContext::class)->companyId())],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = Project::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('projects.backlog.index', $project)->with('success', 'Projeto criado.');
    }
}
