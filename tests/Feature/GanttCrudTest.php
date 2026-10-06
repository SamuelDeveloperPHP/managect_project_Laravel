<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GanttCrudTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project, 2: ProjectBacklog} */
    private function scenario(): array
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Portal', 'code' => 'PORTAL']);
        $backlog = ProjectBacklog::create(['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BL-1', 'name' => 'Backlog']);

        return [$admin, $project, $backlog];
    }

    private function task(array $overrides = []): array
    {
        $start = now()->startOfDay()->getTimestampMs();

        return array_merge([
            'id' => 'tmp_1791322244118', 'name' => 'Nova tarefa', 'code' => 'T1', 'level' => 0, 'status' => 'STATUS_ACTIVE',
            'progress' => 0, 'start' => $start, 'end' => $start + 86_400_000, 'duration' => 2, 'depends' => '', 'assigs' => [],
        ], $overrides);
    }

    public function test_new_tasks_with_temporary_editor_ids_can_be_created_edited_and_removed(): void
    {
        [$admin, $project, $backlog] = $this->scenario();
        $url = route('projects.gantt.save', [$project, $backlog]);

        // Create: the editor sends "tmp_..." ids for rows that do not exist yet.
        $response = $this->actingAs($admin)->postJson($url, ['tasks' => [$this->task(), $this->task(['id' => 'tmp_1791322244999', 'name' => 'Filha', 'code' => 'T2', 'level' => 1])]])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(2, GanttTask::withoutGlobalScopes()->where('project_backlog_id', $backlog->id)->count());

        // Update: persisted ids come back as integers and are edited in place.
        $saved = $response->json('project.tasks');
        $this->actingAs($admin)->postJson($url, ['tasks' => [
            $this->task(['id' => $saved[0]['id'], 'name' => 'Renomeada', 'progress' => 40]),
            $this->task(['id' => $saved[1]['id'], 'name' => 'Filha', 'level' => 1]),
        ]])->assertOk();
        $this->assertDatabaseHas('gantt_tasks', ['id' => $saved[0]['id'], 'name' => 'Renomeada', 'progress' => 40]);
        $this->assertSame(2, GanttTask::withoutGlobalScopes()->where('project_backlog_id', $backlog->id)->count());

        // Delete: tasks missing from the payload are removed.
        $this->actingAs($admin)->postJson($url, ['tasks' => [$this->task(['id' => $saved[0]['id'], 'name' => 'Renomeada'])]])->assertOk();
        $this->assertSame(1, GanttTask::withoutGlobalScopes()->where('project_backlog_id', $backlog->id)->count());
    }

    public function test_invalid_gantt_data_returns_a_readable_portuguese_message(): void
    {
        [$admin, $project, $backlog] = $this->scenario();

        $this->actingAs($admin)->postJson(route('projects.gantt.save', [$project, $backlog]), ['tasks' => [$this->task(['progress' => 150])]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O progresso deve ser um número inteiro entre 0 e 100.');
    }
}
