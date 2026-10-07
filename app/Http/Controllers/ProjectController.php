<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use App\Services\PdfSafetyScanner;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProjectController extends Controller
{
    private const STATUSES = [
        'planning' => 'Planejamento', 'active' => 'Em andamento', 'in_progress' => 'Em andamento',
        'on_hold' => 'Pausado', 'completed' => 'Concluído', 'cancelled' => 'Cancelado',
    ];

    private const MAX_ATTACHMENTS = 5;

    /** Image plus attachments of one project may not exceed this many bytes. */
    public const MAX_PROJECT_FILES_BYTES = 50 * 1024 * 1024;

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

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->requireActiveCompany()) {
            return $redirect;
        }

        return Inertia::render('Projects/Form', [
            'mode' => 'create', 'project' => null, 'members' => $this->teamMembers(), 'filesLimitBytes' => self::MAX_PROJECT_FILES_BYTES, 'filesUsedBytes' => 0,
            'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireActiveCompany()) {
            return $redirect;
        }

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
            'members' => $this->teamMembers($project), 'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES,
            'filesLimitBytes' => self::MAX_PROJECT_FILES_BYTES, 'filesUsedBytes' => $this->projectFilesBytes($project),
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
        $companyId = $project?->company_id ?? app(TenantContext::class)->companyId();
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
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'mimes:pdf,csv,txt,png,jpg,jpeg', 'max:10240'],
        ], [
            'deadline.after_or_equal' => 'O prazo deve ser igual ou posterior à data de início.',
            'image.mimes' => 'A imagem deve estar em JPG, PNG ou WebP.',
            'attachments.*.mimes' => 'Os anexos podem ser PDF, CSV, TXT, PNG ou JPG.',
            'attachments.*.max' => 'Cada anexo pode ter no máximo 10 MB.',
        ]);

        $this->assertFilesWithinLimits($request, $project);

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

    private function requireActiveCompany(): ?RedirectResponse
    {
        if (app(TenantContext::class)->companyId() !== null) {
            return null;
        }

        return redirect()->route('master.companies.index')
            ->withErrors(['company' => 'Selecione a empresa em que o projeto será criado.']);
    }

    private function assertFilesWithinLimits(Request $request, ?Project $project): void
    {
        $attachments = array_values(array_filter($request->file('attachments', []), fn ($file) => $file instanceof UploadedFile));

        if (count($attachments) + ($project?->attachments()->count() ?? 0) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages(['attachments' => 'Um projeto pode ter no máximo '.self::MAX_ATTACHMENTS.' anexos. Remova um arquivo antes de enviar outro.']);
        }

        $image = $request->file('image');
        $used = $project ? $this->projectFilesBytes($project, withoutImage: $image instanceof UploadedFile) : 0;
        $incoming = array_sum(array_map(fn (UploadedFile $file) => (int) $file->getSize(), $attachments))
            + ($image instanceof UploadedFile ? (int) $image->getSize() : 0);

        if ($used + $incoming > self::MAX_PROJECT_FILES_BYTES) {
            throw ValidationException::withMessages(['attachments' => sprintf(
                'Os arquivos do projeto somam no máximo %d MB. Este envio ultrapassa o limite (já usado: %s MB).',
                self::MAX_PROJECT_FILES_BYTES / 1048576,
                number_format($used / 1048576, 1, ',', '.'),
            )]);
        }

        $pdfs = array_filter($attachments, fn (UploadedFile $file) => strtolower($file->getClientOriginalExtension()) === 'pdf');
        foreach ($pdfs as $pdf) {
            try {
                app(PdfSafetyScanner::class)->assertSafe($pdf);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['attachments' => $exception->getMessage()]);
            }
        }
    }

    private function projectFilesBytes(Project $project, bool $withoutImage = false): int
    {
        $bytes = (int) $project->attachments()->sum('size_bytes');

        if (! $withoutImage && $project->image_path && Storage::disk('public')->exists($project->image_path)) {
            $bytes += (int) Storage::disk('public')->size($project->image_path);
        }

        return $bytes;
    }

    private function teamMembers(?Project $project = null): array
    {
        return User::query()->where('company_id', $project?->company_id ?? app(TenantContext::class)->companyId())
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
            if ($oldPath) {
                $changes['removed'][] = ['disk' => 'public', 'path' => $oldPath];
            }
        }

        foreach ($request->file('attachments', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
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
        foreach ($files as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }

    private function deleteReplacedFiles(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }

    private function projectCard(Project $project): array
    {
        return $project->only(['id', 'name', 'code', 'description', 'status', 'client', 'priority', 'start_date', 'deadline']) + [
            'backlogs_count' => $project->backlogs_count, 'tasks_count' => $project->tasks_count,
            'image_url' => $project->image_path ? Storage::disk('public')->url($project->image_path) : null,
        ];
    }
}
