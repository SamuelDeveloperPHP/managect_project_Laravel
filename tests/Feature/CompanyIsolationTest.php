<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_cannot_open_another_company_project_by_url(): void
    {
        [$companyA, $userA, $projectA] = $this->companyWithProject('empresa-a', 'Empresa A');
        [, , $projectB] = $this->companyWithProject('empresa-b', 'Empresa B');

        $this->actingAs($userA)
            ->get(route('projects.backlog.index', $projectA))
            ->assertOk();

        $this->actingAs($userA)
            ->get(route('projects.backlog.index', $projectB))
            ->assertNotFound();
    }

    public function test_item_created_in_project_receives_the_authenticated_company(): void
    {
        [$company, $user, $project, $backlog] = $this->companyWithProject('empresa-a', 'Empresa A');

        $this->actingAs($user)->post(route('projects.backlog.items.store', [$project, $backlog]), [
            'code' => 'BL-01-01',
            'epic' => 'Plataforma',
            'title' => 'Isolamento por empresa',
            'priority' => 'P0',
            'release' => 'R0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_backlog_items', [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'project_backlog_id' => $backlog->id,
            'code' => 'BL-01-01',
        ]);
    }

    public function test_backlog_is_a_separate_area_inside_the_project(): void
    {
        [, $user, $project] = $this->companyWithProject('empresa-a', 'Empresa A');

        $this->actingAs($user)->post(route('projects.backlog.store', $project), ['code' => 'BL-NOVO', 'name' => 'Backlog novo'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_backlogs', ['project_id' => $project->id, 'code' => 'BL-NOVO']);
    }

    public function test_project_edit_page_resolves_within_the_users_company_and_for_master(): void
    {
        [$companyA, $userA, $projectA] = $this->companyWithProject('empresa-a', 'Empresa A');
        [, , $projectB] = $this->companyWithProject('empresa-b', 'Empresa B');

        $this->actingAs($userA)->get(route('projects.edit', $projectA))->assertOk();
        $this->actingAs($userA)->get(route('projects.edit', $projectB))->assertNotFound();

        Company::query()->find(2) ?? Company::factory()->create(['id' => 2]);
        $master = User::factory()->create(['company_id' => 2, 'email' => User::PLATFORM_MASTER_EMAIL, 'role' => 'master']);
        $this->actingAs($master)->get(route('projects.edit', $projectB))->assertOk();
    }

    public function test_backlog_code_is_generated_when_omitted_and_stays_unique(): void
    {
        [, $user, $project] = $this->companyWithProject('empresa-a', 'Empresa A');

        $this->actingAs($user)->post(route('projects.backlog.store', $project), ['name' => 'Segundo'])->assertSessionHasNoErrors();
        $this->post(route('projects.backlog.store', $project), ['name' => 'Terceiro'])->assertSessionHasNoErrors();
        $this->post(route('projects.backlog.store', $project), ['code' => 'custom', 'name' => 'Manual'])->assertSessionHasNoErrors();
        $this->post(route('projects.backlog.store', $project), ['code' => 'CUSTOM', 'name' => 'Repetido'])->assertSessionHasErrors('code');

        $codes = ProjectBacklog::withoutGlobalScopes()->where('project_id', $project->id)->orderBy('id')->pluck('code')->all();
        $this->assertSame(['BL-GERAL', 'BL-02', 'BL-03', 'CUSTOM'], $codes);
    }

    private function companyWithProject(string $slug, string $name): array
    {
        $company = Company::create(compact('slug', 'name'));
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'is_active' => true]);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto '.$name, 'code' => strtoupper($slug), 'created_by' => $user->id]);

        $backlog = ProjectBacklog::create(['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BL-GERAL', 'name' => 'Backlog geral']);

        return [$company, $user, $project, $backlog];
    }
}
