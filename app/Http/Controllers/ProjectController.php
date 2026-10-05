<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    private const STATUSES = [
        'planning' => 'Planejamento', 'active' => 'Em andamento', 'in_progress' => 'Em andamento',
        'on_hold' => 'Pausado', 'completed' => 'Concluído', 'cancelled' => 'Cancelado',
    ];

    private const PRIORITIES = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'critical' => 'Crítica'];

    public function index(Request $request): Response
    {
        return Inertia::render('Projects/Index', [
            'projects' => Project::query()->withCount(['backlogs', 'tasks'])->latest()->get([
                'id', 'name', 'code', 'description', 'status', 'client', 'priority', 'start_date', 'deadline', 'image_path', 'created_at',
            ])->map(fn (Project $project): array => $this->projectCard($project)),
            'canManageProjects' => $request->user()->hasPermission('can_manage_projects'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Projects/Form', [
            'mode' => 'create', 'project' => null, 'members' => $this->teamMembers(),
            'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedProject($request);
        $memberIds = $this->memberIds($data);
        unset($data['member_ids'], $data['image'], $data['attachments']);
        $data['code'] = $this->projectCode($data['code'] ?? null, $data['name']);

        $fileChanges = ['added' => [], 'removed' => []];
        try {
            $project = DB::transaction(function () use ($request, $data, $memberIds, &$fileChanges): Project {
                $project = Project::create($data);
                $project->forceFill(['created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
                $project->members()->sync($memberIds);
                $project->forceFill(['leader_id' => $data['leader_id'] ?? null])->save();
                $this->storeFiles($request, $project, $fileChanges);
                return $project;
            });
        } catch (Throwable $exception) {
            $this->discardNewFiles($fileChanges['added']);
            throw $exception;
        }
        $this->deleteReplacedFiles($fileChanges['removed']);

        return redirect()->route('projects.overview', $project)->with('success', 'Projeto cadastrado. Agora você pode criar os backlogs e as tarefas.');
    }

    public function edit(Project $project): Response
    {
        $project->load(['members:id', 'attachments:id,project_id,original_name,size_bytes']);
        $project->loadCount(['backlogs', 'tasks']);

        return Inertia::render('Projects/Form', [
            'mode' => 'edit',
            'project' => [
                'id' => $project->id, 'name' => $project->name, 'code' => $project->code,
                'client' => $project->client ?? '', 'status' => $project->status, 'priority' => $project->priority,
                'budget' => $project->budget === null ? '' : (string) $project->budget,
                'start_date' => $project->start_date?->format('Y-m-d') ?? '', 'deadline' => $project->deadline?->format('Y-m-d') ?? '',
                'description' => $project->description ?? '', 'leader_id' => $project->leader_id ?? '',
                'member_ids' => $project->members->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                'image_url' => $project->image_path ? Storage::disk('public')->url($project->image_path) : null,
                'attachments' => $project->attachments->map(fn (ProjectAttachment $file): array => [
                    'id' => $file->id, 'name' => $file->original_name, 'size_bytes' => $file->size_bytes,
                ])->all(), 'backlogs_count' => $project->backlogs_count, 'tasks_count' => $project->tasks_count,
            ],
            'members' => $this->teamMembers(), 'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedProject($request, $project);
        $memberIds = $this->memberIds($data);
        unset($data['member_ids'], $data['image'], $data['attachments']);
        $data['code'] = $this->projectCode($data['code'] ?? null, $data['name'], $project);

        $fileChanges = ['added' => [], 'removed' => []];
        try {
            DB::transaction(function () use ($request, $project, $data, $memberIds, &$fileChanges): void {
                $project->fill($data)->forceFill([
                    'leader_id' => $data['leader_id'] ?? null,
                    'updated_by' => $request->user()->id,
                ])->save();
                $project->members()->sync($memberIds);
                $this->storeFiles($request, $project, $fileChanges);
            });
        } catch (Throwable $exception) {
            $this->discardNewFiles($fileChanges['added']);
            throw $exception;
        }
        $this->deleteReplacedFiles($fileChanges['removed']);

        return redirect()->route('projects.overview', $project)->with('success', 'Projeto atualizado.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->backlogs()->exists() || $project->tasks()->exists()) {
            throw ValidationException::withMessages(['project' => 'Este projeto possui backlogs ou tarefas. Remova ou reorganize esses dados antes de excluir o projeto.']);
        }

        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Projeto excluído.');
    }

    public function downloadAttachment(Project $project, ProjectAttachment $attachment): mixed
    {
        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);
        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroyAttachment(Project $project, ProjectAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->project_id === $project->id, 404);
        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();
        return back()->with('success', 'Anexo removido.');
    }

    private function validatedProject(Request $request, ?Project $project = null): array
    {
        $companyId = app(TenantContext::class)->companyId();
        $userRule = Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at'));

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('projects', 'code')->where('company_id', $companyId)->ignore($project?->id)],
            'client' => ['nullable', 'string', 'max:190'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(self::PRIORITIES))],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:10000'],
            'leader_id' => ['nullable', 'integer', $userRule],
            'member_ids' => ['nullable', 'array', 'max:100'],
            'member_ids.*' => ['integer', 'distinct', $userRule],
            'image' => ['nullable', 'image', 'max:4096'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,zip', 'max:10240'],
        ], [
            'deadline.after_or_equal' => 'O prazo deve ser igual ou posterior à data de início.',
            'image.image' => 'Envie um arquivo de imagem válido.',
            'attachments.*.max' => 'Cada anexo pode ter no máximo 10 MB.',
        ]);

        if ($project && count($data['attachments'] ?? []) + $project->attachments()->count() > 5) {
            throw ValidationException::withMessages(['attachments' => 'Um projeto pode ter no máximo 5 anexos. Remova um arquivo antes de enviar outro.']);
        }

        return $data;
    }

    private function memberIds(array $data): array
    {
        $ids = array_map('intval', $data['member_ids'] ?? []);
        if (! empty($data['leader_id'])) {
            $ids[] = (int) $data['leader_id'];
        }
        return array_values(array_unique($ids));
    }

    private function teamMembers(): array
    {
        return User::query()->where('company_id', app(TenantContext::class)->companyId())
            ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])->toArray();
    }

    private function projectCode(?string $code, string $name, ?Project $ignore = null): string
    {
        $base = Str::upper(Str::limit(Str::slug($code ?: $name), 40, '')) ?: 'PROJETO';
        $candidate = $base;
        $suffix = 2;
        while (Project::query()->where('code', $candidate)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
            $tail = '-'.$suffix++;
            $candidate = Str::limit($base, 40 - strlen($tail), '').$tail;
        }
        return $candidate;
    }

    private function storeFiles(Request $request, Project $project, array &$changes): void
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('project-images/'.$project->company_id.'/'.$project->id, 'public');
            abort_if(! $path, 500, 'Não foi possível armazenar a imagem do projeto.');
            $oldPath = $project->image_path;
            $project->forceFill(['image_path' => $path])->save();
            $changes['added'][] = ['disk' => 'public', 'path' => $path];
            if ($oldPath) $changes['removed'][] = ['disk' => 'public', 'path' => $oldPath];
        }

        foreach ($request->file('attachments', []) as $file) {
            if (! $file instanceof UploadedFile) continue;
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('project-attachments/'.$project->company_id.'/'.$project->id, Str::uuid().'.'.$extension, 'local');
            abort_if(! $path, 500, 'Não foi possível armazenar um anexo do projeto.');
            $changes['added'][] = ['disk' => 'local', 'path' => $path];
            $name = preg_replace('/[^\pL\pN ._()\-]/u', '_', basename($file->getClientOriginalName())) ?: 'anexo';
            ProjectAttachment::create([
                'project_id' => $project->id, 'uploaded_by' => $request->user()->id,
                'original_name' => mb_substr($name, 0, 190), 'stored_path' => $path,
                'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
            ]);
        }
    }

    private function discardNewFiles(array $files): void
    {
        foreach ($files as $file) Storage::disk($file['disk'])->delete($file['path']);
    }

    private function deleteReplacedFiles(array $files): void
    {
        foreach ($files as $file) Storage::disk($file['disk'])->delete($file['path']);
    }

    private function projectCard(Project $project): array
    {
        return $project->only(['id', 'name', 'code', 'description', 'status', 'client', 'priority', 'start_date', 'deadline']) + [
            'backlogs_count' => $project->backlogs_count, 'tasks_count' => $project->tasks_count,
            'image_url' => $project->image_path ? Storage::disk('public')->url($project->image_path) : null,
        ];
    }
}
