<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regra: quem tem can_manage_projects só altera os projetos de que participa (líder, membro ou criador). */
class ProjectAccessRuleTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = ['name' => 'Projeto', 'status' => 'active', 'priority' => 'medium'];

    public function test_a_manager_who_is_not_in_the_project_cannot_change_it(): void
    {
        [, , $project, $backlog, $outsider] = $this->scenario();

        $this->actingAs($outsider)->get(route('projects.edit', $project))->assertForbidden();
        $this->put(route('projects.update', $project), self::PAYLOAD)->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();
        $this->post(route('projects.backlog.store', $project), ['name' => 'Novo'])->assertForbidden();
        $this->post(route('projects.backlog.items.store', [$project, $backlog]), [
            'code' => 'X-1', 'epic' => 'E', 'title' => 'T', 'priority' => 'P1',
        ])->assertForbidden();
        $this->postJson(route('projects.gantt.save', [$project, $backlog]), ['tasks' => []])->assertForbidden();
    }

    public function test_the_outsider_can_still_read_everything_but_gets_no_write_flags(): void
    {
        [, , $project, $backlog, $outsider] = $this->scenario();

        $this->actingAs($outsider);
        $this->get(route('projects.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManageProjects', true)->where('manageableProjectIds', []));
        $this->get(route('projects.backlog.index', $project))->assertOk()->assertInertia(fn ($page) => $page->where('canManage', false));
        $this->get(route('projects.backlog.show', [$project, $backlog]))->assertOk()->assertInertia(fn ($page) => $page->where('canManage', false));
        $this->get(route('projects.timeline.index', [$project, $backlog]))->assertOk()->assertInertia(fn ($page) => $page->where('canManage', false));
        $this->getJson(route('projects.gantt.show', [$project, $backlog]))->assertOk()->assertJsonPath('project.canWrite', false);
    }

    public function test_members_leaders_and_creators_can_change_the_project(): void
    {
        [$company, $admin, $project, $backlog] = $this->scenario();

        $member = $this->manager($company);
        $project->members()->attach($member->id);
        $leader = $this->manager($company);
        $project->forceFill(['leader_id' => $leader->id])->save();
        $creator = $this->manager($company);
        $project->forceFill(['created_by' => $creator->id])->save();

        foreach ([$member, $leader, $creator] as $person) {
            $this->actingAs($person)->get(route('projects.edit', $project))->assertOk();
            $this->post(route('projects.backlog.store', $project), ['name' => 'Backlog de '.$person->id])->assertSessionHasNoErrors();
            $this->get(route('projects.backlog.index', $project))->assertInertia(fn ($page) => $page->where('canManage', true));
            $this->get(route('projects.index'))->assertInertia(fn ($page) => $page->where('manageableProjectIds', [$project->id]));
            $this->getJson(route('projects.gantt.show', [$project, $backlog]))->assertJsonPath('project.canWrite', true);
        }
    }

    public function test_participating_without_the_permission_is_not_enough(): void
    {
        [$company, , $project] = $this->scenario();
        $viewer = User::factory()->create(['company_id' => $company->id, 'role' => 'user', 'permissions' => []]);
        $project->members()->attach($viewer->id);

        $this->actingAs($viewer)->get(route('projects.edit', $project))->assertForbidden();
        $this->get(route('projects.index'))->assertInertia(fn ($page) => $page->where('manageableProjectIds', []));
    }

    public function test_admins_and_the_master_manage_every_project_of_the_company(): void
    {
        [, $admin, $project, $backlog] = $this->scenario();

        $this->actingAs($admin)->get(route('projects.edit', $project))->assertOk();
        $this->put(route('projects.update', $project), self::PAYLOAD)->assertSessionHasNoErrors();
        $this->get(route('projects.index'))->assertInertia(fn ($page) => $page->where('manageableProjectIds', [$project->id]));
        $this->getJson(route('projects.gantt.show', [$project, $backlog]))->assertJsonPath('project.canWrite', true);
    }

    public function test_removing_someone_from_the_project_takes_the_access_away(): void
    {
        [$company, $admin, $project] = $this->scenario();
        $member = $this->manager($company);
        $project->members()->attach($member->id);

        $this->actingAs($member)->get(route('projects.edit', $project))->assertOk();
        $this->actingAs($admin)->put(route('projects.update', $project), self::PAYLOAD + ['member_ids' => []])->assertSessionHasNoErrors();
        $this->actingAs($member)->get(route('projects.edit', $project))->assertForbidden();
    }

    /** @return array{0: Company, 1: User, 2: Project, 3: ProjectBacklog, 4: User} */
    private function scenario(): array
    {
        $company = Company::create(['slug' => 'acesso', 'name' => 'Empresa Acesso']);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'is_active' => true]);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto A', 'code' => 'A', 'created_by' => $admin->id]);
        $backlog = ProjectBacklog::create(['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BL-01', 'name' => 'Backlog']);

        return [$company, $admin, $project, $backlog, $this->manager($company)];
    }

    private function manager(Company $company): User
    {
        return User::factory()->create([
            'company_id' => $company->id, 'role' => 'user', 'is_active' => true,
            'permissions' => ['can_manage_projects' => true, 'can_view_reports' => false],
        ]);
    }
}
