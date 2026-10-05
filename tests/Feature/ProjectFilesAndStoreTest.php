<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFilesAndStoreTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = ['status' => 'planning', 'priority' => 'low'];

    public function test_store_creates_project_with_generated_code_and_resolves_collisions(): void
    {
        [$company, $admin] = $this->companyWithAdmin('a');
        $leader = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);
        $payload = ['name' => 'Portal do Cliente', 'leader_id' => $leader->id, 'budget' => '1500.50'] + self::BASE;

        $this->actingAs($admin)->post(route('projects.store'), $payload)->assertSessionHasNoErrors();
        $first = Project::withoutGlobalScopes()->where('name', 'Portal do Cliente')->firstOrFail();
        $this->assertSame('PORTAL-DO-CLIENTE', $first->code);
        $this->assertSame($company->id, (int) $first->company_id);
        $this->assertSame($admin->id, (int) $first->created_by);
        $this->assertSame($leader->id, (int) $first->leader_id);
        $this->assertTrue($first->members()->whereKey($leader->id)->exists());

        $this->post(route('projects.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(
            ['PORTAL-DO-CLIENTE', 'PORTAL-DO-CLIENTE-2'],
            Project::withoutGlobalScopes()->where('name', 'Portal do Cliente')->orderBy('id')->pluck('code')->all(),
        );

        $this->post(route('projects.store'), ['name' => 'Outro', 'code' => 'meu código'] + self::BASE)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['name' => 'Outro', 'code' => 'MEU-CODIGO']);
    }

    public function test_store_validates_required_fields_and_deadline(): void
    {
        [, $admin] = $this->companyWithAdmin('a');

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'ab', 'status' => 'xx', 'priority' => 'yy', 'start_date' => '2026-10-10', 'deadline' => '2026-10-01',
        ])->assertSessionHasErrors(['name', 'status', 'priority', 'deadline']);
    }

    public function test_master_must_select_a_company_before_creating_a_project(): void
    {
        $master = $this->master();
        [$company] = $this->companyWithAdmin('a');
        app(TenantContext::class)->clear();
        $payload = ['name' => 'Projeto Master'] + self::BASE;

        $this->actingAs($master)->get(route('projects.create'))->assertRedirect(route('master.companies.index'));
        $this->post(route('projects.store'), $payload)->assertRedirect(route('master.companies.index'))->assertSessionHasErrors('company');

        $this->withSession(['master_company_id' => $company->id])->post(route('projects.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['name' => 'Projeto Master', 'company_id' => $company->id]);
    }

    public function test_project_image_is_stored_on_the_public_disk_and_replaced(): void
    {
        Storage::fake('public');
        [, $admin] = $this->companyWithAdmin('a');
        $payload = ['name' => 'Com imagem'] + self::BASE;

        $this->actingAs($admin)->post(route('projects.store'), $payload + ['image' => UploadedFile::fake()->image('capa.png', 200, 120)])
            ->assertSessionHasNoErrors();
        $project = Project::withoutGlobalScopes()->where('name', 'Com imagem')->firstOrFail();
        Storage::disk('public')->assertExists($project->image_path);
        $old = $project->image_path;

        $this->put(route('projects.update', $project), $payload + ['image' => UploadedFile::fake()->image('nova.jpg', 100, 100)])
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($project->fresh()->image_path);

        $this->put(route('projects.update', $project), $payload + ['image' => UploadedFile::fake()->create('script.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('image');
        $this->put(route('projects.update', $project), $payload + ['image' => UploadedFile::fake()->create('grande.png', 5000, 'image/png')])
            ->assertSessionHasErrors('image');
    }

    public function test_a_project_accepts_at_most_five_attachments(): void
    {
        Storage::fake('local');
        [, $admin, $project] = $this->companyWithAdmin('a', withProject: true);
        $payload = ['name' => 'Projeto'] + self::BASE;
        $files = fn (int $count) => array_map(fn ($i) => UploadedFile::fake()->create("doc{$i}.txt", 10, 'text/plain'), range(1, $count));

        $this->actingAs($admin)->put(route('projects.update', $project), $payload + ['attachments' => $files(6)])->assertSessionHasErrors('attachments');
        $this->put(route('projects.update', $project), $payload + ['attachments' => $files(4)])->assertSessionHasNoErrors();
        $this->put(route('projects.update', $project), $payload + ['attachments' => $files(2)])->assertSessionHasErrors('attachments');
        $this->assertSame(4, ProjectAttachment::query()->where('project_id', $project->id)->count());

        $this->post(route('projects.store'), ['name' => 'Novo', 'attachments' => $files(6)] + self::BASE)->assertSessionHasErrors('attachments');
    }

    public function test_all_project_files_are_limited_to_fifty_megabytes(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [, $admin, $project] = $this->companyWithAdmin('a', withProject: true);
        $payload = ['name' => 'Projeto'] + self::BASE;
        $tenMb = fn (string $name) => UploadedFile::fake()->create($name, 10240, 'text/plain');
        $fiveFiles = fn () => array_map($tenMb, ['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt']);

        $this->actingAs($admin)->put(route('projects.update', $project), $payload + ['attachments' => $fiveFiles()])->assertSessionHasNoErrors();
        $this->assertSame(50 * 1048576, (int) ProjectAttachment::query()->where('project_id', $project->id)->sum('size_bytes'));

        $this->put(route('projects.update', $project), $payload + ['image' => UploadedFile::fake()->image('capa.png', 50, 50)])
            ->assertSessionHasErrors('attachments');

        $this->delete(route('projects.attachments.destroy', [$project, ProjectAttachment::query()->where('project_id', $project->id)->firstOrFail()]));
        $this->put(route('projects.update', $project), $payload + ['image' => UploadedFile::fake()->image('capa.png', 50, 50)])
            ->assertSessionHasNoErrors();

        $this->post(route('projects.store'), ['name' => 'Novo', 'attachments' => $fiveFiles(), 'image' => UploadedFile::fake()->image('x.png', 50, 50)] + self::BASE)
            ->assertSessionHasErrors('attachments');
    }

    public function test_pdf_without_security_scanners_and_unsafe_file_types_are_rejected(): void
    {
        Storage::fake('local');
        config(['security.pdf_validator_binary' => null, 'security.antivirus_binary' => null]);
        [, $admin, $project] = $this->companyWithAdmin('a', withProject: true);
        $payload = ['name' => 'Projeto'] + self::BASE;

        $this->actingAs($admin)->put(route('projects.update', $project), $payload + ['attachments' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('attachments');
        foreach (['malware.zip', 'macro.docx', 'run.exe', 'page.html'] as $name) {
            $this->put(route('projects.update', $project), $payload + ['attachments' => [UploadedFile::fake()->create($name, 10)]])
                ->assertSessionHasErrors('attachments.0');
        }
        $this->assertSame(0, ProjectAttachment::query()->where('project_id', $project->id)->count());
    }

    public function test_master_edits_a_project_of_any_company_with_its_own_team(): void
    {
        $master = $this->master();
        [$company, , $project] = $this->companyWithAdmin('a', withProject: true);
        $leader = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);
        app(TenantContext::class)->clear();

        $this->actingAs($master)->put(route('projects.update', $project), ['name' => 'Editado pelo Master', 'leader_id' => $leader->id] + self::BASE)
            ->assertSessionHasNoErrors();
        $this->assertSame($leader->id, (int) $project->fresh()->leader_id);
    }

    private function master(): User
    {
        Company::query()->find(2) ?? Company::factory()->create(['id' => 2]);

        return User::factory()->create(['company_id' => 2, 'email' => User::PLATFORM_MASTER_EMAIL, 'role' => 'master']);
    }

    private function companyWithAdmin(string $slug, bool $withProject = false): array
    {
        $company = Company::create(['slug' => $slug, 'name' => 'Empresa '.$slug]);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'is_active' => true]);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = $withProject ? Project::create(['name' => 'Projeto '.$slug, 'code' => strtoupper($slug), 'created_by' => $admin->id]) : null;

        return [$company, $admin, $project];
    }
}
