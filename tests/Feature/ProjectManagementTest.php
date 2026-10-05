<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_project_with_leader_and_team_from_the_same_company(): void
    {
        [$company, $admin, $project] = $this->companyWithProject('a');
        $leader = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);
        $member = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);

        $this->actingAs($admin)->put(route('projects.update', $project), [
            'name' => 'Projeto renomeado', 'code' => 'RENOMEADO', 'status' => 'active', 'priority' => 'high',
            'client' => 'Cliente X', 'leader_id' => $leader->id, 'member_ids' => [$member->id],
        ])->assertRedirect(route('projects.overview', $project))->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('Projeto renomeado', $project->name);
        $this->assertSame($leader->id, (int) $project->leader_id);
        $this->assertEqualsCanonicalizing([$leader->id, $member->id], $project->members()->pluck('users.id')->all());
    }

    public function test_leader_and_members_from_another_company_or_inactive_are_rejected(): void
    {
        [$company, $admin, $project] = $this->companyWithProject('a');
        $outsider = User::factory()->create(['role' => 'user']);
        $inactive = User::factory()->create(['company_id' => $company->id, 'role' => 'user', 'is_active' => false]);
        $payload = ['name' => 'Projeto', 'status' => 'active', 'priority' => 'medium'];

        $this->actingAs($admin)->put(route('projects.update', $project), $payload + ['leader_id' => $outsider->id])
            ->assertSessionHasErrors('leader_id');
        $this->put(route('projects.update', $project), $payload + ['member_ids' => [$inactive->id]])
            ->assertSessionHasErrors('member_ids.0');
    }

    public function test_attachments_are_stored_privately_downloaded_in_scope_and_removed(): void
    {
        Storage::fake('local');
        [$company, $admin, $project] = $this->companyWithProject('a');
        [, $otherAdmin] = $this->companyWithProject('b');

        $this->actingAs($admin)->put(route('projects.update', $project), [
            'name' => 'Projeto', 'status' => 'active', 'priority' => 'medium',
            'attachments' => [UploadedFile::fake()->create('escopo.txt', 20, 'text/plain')],
        ])->assertSessionHasNoErrors();

        $attachment = ProjectAttachment::query()->where('project_id', $project->id)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->stored_path);

        $this->get(route('projects.attachments.download', [$project, $attachment]))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($otherAdmin)->get(route('projects.attachments.download', [$project, $attachment]))->assertNotFound();

        $this->actingAs($admin)->delete(route('projects.attachments.destroy', [$project, $attachment]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('project_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->stored_path);
    }

    public function test_project_with_backlogs_cannot_be_deleted_but_an_empty_one_can(): void
    {
        [$company, $admin, $project] = $this->companyWithProject('a');
        $backlog = ProjectBacklog::create(['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BL-1', 'name' => 'Backlog']);

        $this->actingAs($admin)->delete(route('projects.destroy', $project))->assertSessionHasErrors('project');
        $this->assertNotSoftDeleted('projects', ['id' => $project->id]);

        $backlog->delete();
        $this->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_user_without_project_permission_cannot_edit_update_or_delete(): void
    {
        [$company, , $project] = $this->companyWithProject('a');
        $viewer = User::factory()->create(['company_id' => $company->id, 'role' => 'user', 'permissions' => []]);

        $this->actingAs($viewer)->get(route('projects.edit', $project))->assertForbidden();
        $this->put(route('projects.update', $project), ['name' => 'Projeto', 'status' => 'active', 'priority' => 'medium'])->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();
    }

    private function companyWithProject(string $slug): array
    {
        $company = Company::create(['slug' => $slug, 'name' => 'Empresa '.$slug]);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'is_active' => true]);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto '.$slug, 'code' => strtoupper($slug), 'created_by' => $admin->id]);

        return [$company, $admin, $project];
    }
}
