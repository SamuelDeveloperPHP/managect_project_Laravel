<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminDashboardAndTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_the_authenticated_company_metrics(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto A', 'code' => 'PROJ-A']);
        app(TenantContext::class)->setCompanyId($otherCompany->id);
        Project::create(['company_id' => $otherCompany->id, 'name' => 'Projeto B', 'code' => 'PROJ-B']);
        DB::table('gantt_tasks')->insert([
            ['phalcon_id' => 11, 'company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tarefa da empresa A', 'status' => 'STATUS_ACTIVE', 'progress' => 0, 'start_at' => now(), 'end_at' => now()->addDay(), 'duration' => 1, 'depends' => '', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['phalcon_id' => 12, 'company_id' => $otherCompany->id, 'project_id' => null, 'name' => 'Tarefa da empresa B', 'status' => 'STATUS_ACTIVE', 'progress' => 0, 'start_at' => now(), 'end_at' => now()->addDay(), 'duration' => 1, 'depends' => '', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.projects', 1)
                ->where('stats.tasks', 1)
                ->where('upcomingTasks.0.name', 'Tarefa da empresa A')
                ->missing('upcomingTasks.1'));
    }

    public function test_master_dashboard_defaults_to_all_companies_and_counts_unique_online_users_per_company(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 120, 'session.table' => 'sessions']);
        $companyA = Company::factory()->create(['name' => 'Empresa A']);
        $companyB = Company::factory()->create(['name' => 'Empresa B']);
        $online = User::factory()->create(['company_id' => $companyA->id, 'role' => 'admin', 'is_active' => true]);
        User::factory()->create(['company_id' => $companyA->id, 'role' => 'user', 'is_active' => true]);
        User::factory()->create(['company_id' => $companyA->id, 'role' => 'user', 'is_active' => false]);
        User::factory()->create(['company_id' => $companyB->id, 'role' => 'admin', 'is_active' => true]);
        $master = User::factory()->create(['company_id' => $companyA->id, 'role' => 'master', 'is_active' => true]);

        DB::table('sessions')->insert([
            ['id' => 'first-session', 'user_id' => $online->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'second-session', 'user_id' => $online->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($master)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('selectedCompanyId', null)
                ->where('stats.team_members', 3)
                ->has('companyMetrics', 2)
                ->where('companyMetrics.0.name', 'Empresa A')
                ->where('companyMetrics.0.total_users', 3)
                ->where('companyMetrics.0.active_users', 2)
                ->where('companyMetrics.0.online_users', 1)
                ->where('companyMetrics.0.offline_users', 1)
                ->where('companyMetrics.1.name', 'Empresa B')
                ->where('companyMetrics.1.online_users', 0));
    }

    public function test_master_can_open_company_list_and_keep_selected_company_scope_while_navigating(): void
    {
        $companyA = Company::factory()->create(['name' => 'Empresa A']);
        $companyB = Company::factory()->create(['name' => 'Empresa B']);
        $master = User::factory()->create(['company_id' => $companyA->id, 'role' => 'master']);

        app(TenantContext::class)->setCompanyId($companyA->id);
        Project::create(['name' => 'Projeto visível A', 'code' => 'PROJ-A']);
        app(TenantContext::class)->setCompanyId($companyB->id);
        Project::create(['name' => 'Projeto privado B', 'code' => 'PROJ-B']);
        app(TenantContext::class)->clear();

        $this->actingAs($master)->get(route('master.companies.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Master/Companies')
                ->has('companies', 2)
                ->where('companies.0.name', 'Empresa A'));

        $this->post(route('master.companies.select'), ['company_id' => $companyA->id])->assertRedirect(route('dashboard'));
        $this->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Projects/Index')
                ->has('projects', 1)
                ->where('projects.0.name', 'Projeto visível A'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('selectedCompanyId', $companyA->id)->where('stats.projects', 1));
    }

    public function test_project_timeline_is_tenant_scoped_and_displays_imported_tasks(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto do cliente', 'code' => 'CLIENT-1']);
        app(TenantContext::class)->setCompanyId($otherCompany->id);
        $otherProject = Project::create(['name' => 'Projeto externo', 'code' => 'EXTERNAL-1']);
        DB::table('gantt_tasks')->insert([
            ['phalcon_id' => 21, 'company_id' => $company->id, 'project_id' => $project->id, 'name' => 'Tarefa importada', 'status' => 'STATUS_ACTIVE', 'progress' => 25, 'start_at' => now(), 'end_at' => now()->addDay(), 'duration' => 1, 'depends' => '', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['phalcon_id' => 22, 'company_id' => $otherCompany->id, 'project_id' => $otherProject->id, 'name' => 'Tarefa privada', 'status' => 'STATUS_ACTIVE', 'progress' => 0, 'start_at' => now(), 'end_at' => now()->addDay(), 'duration' => 1, 'depends' => '', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->get(route('projects.timeline.index', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Projects/Timeline')
                ->has('tasks', 1)
                ->where('tasks.0.name', 'Tarefa importada'));

        $this->actingAs($user)->get(route('projects.timeline.index', $otherProject))->assertNotFound();
    }
}
