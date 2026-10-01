<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
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
        [$company, $user, $project] = $this->companyWithProject('empresa-a', 'Empresa A');

        $this->actingAs($user)->post(route('projects.backlog.store', $project), [
            'code' => 'BL-01-01',
            'epic' => 'Plataforma',
            'title' => 'Isolamento por empresa',
            'priority' => 'P0',
            'release' => 'R0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_backlog_items', [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'code' => 'BL-01-01',
        ]);
    }

    private function companyWithProject(string $slug, string $name): array
    {
        $company = Company::create(compact('slug', 'name'));
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'is_active' => true]);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto '.$name, 'code' => strtoupper($slug), 'created_by' => $user->id]);

        return [$company, $user, $project];
    }
}
