<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\ProjectBacklogItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

    private function save(User $user, Project $project, ProjectBacklog $backlog, array $tasks, ?int $revision = null): TestResponse
    {
        $revision ??= (int) $backlog->fresh()->gantt_revision;

        return $this->actingAs($user)->postJson(route('projects.gantt.save', [$project, $backlog]), ['revision' => $revision, 'tasks' => $tasks]);
    }

    public function test_new_tasks_with_temporary_editor_ids_can_be_created_edited_and_removed(): void
    {
        [$admin, $project, $backlog] = $this->scenario();

        // Create: the editor sends "tmp_..." ids for rows that do not exist yet.
        $response = $this->save($admin, $project, $backlog, [$this->task(), $this->task(['id' => 'tmp_1791322244999', 'name' => 'Filha', 'code' => 'T2', 'level' => 1])])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(2, GanttTask::withoutGlobalScopes()->where('project_backlog_id', $backlog->id)->count());

        // Update: persisted ids come back as integers and are edited in place.
        $saved = $response->json('project.tasks');
        $this->save($admin, $project, $backlog, [
            $this->task(['id' => $saved[0]['id'], 'name' => 'Renomeada', 'progress' => 40]),
            $this->task(['id' => $saved[1]['id'], 'name' => 'Filha', 'level' => 1]),
        ])->assertOk();
        $this->assertDatabaseHas('gantt_tasks', ['id' => $saved[0]['id'], 'name' => 'Renomeada', 'progress' => 40]);
        $this->assertSame(2, GanttTask::withoutGlobalScopes()->where('project_backlog_id', $backlog->id)->count());

        // Delete: tasks missing from the payload are removed.
        $this->save($admin, $project, $backlog, [$this->task(['id' => $saved[0]['id'], 'name' => 'Renomeada'])])->assertOk();
        $this->assertSame(1, GanttTask::withoutGlobalScopes()->where('project_backlog_id', $backlog->id)->count());
    }

    public function test_invalid_gantt_data_returns_a_readable_portuguese_message(): void
    {
        [$admin, $project, $backlog] = $this->scenario();

        $this->save($admin, $project, $backlog, [$this->task(['progress' => 150])])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O progresso deve ser um número inteiro entre 0 e 100.');
    }

    public function test_predecessors_are_saved_and_circular_ones_are_rejected(): void
    {
        [$admin, $project, $backlog] = $this->scenario();
        $tasks = fn (string $secondDepends, string $firstDepends = '') => [
            $this->task(['id' => 'tmp_1', 'depends' => $firstDepends]),
            $this->task(['id' => 'tmp_2', 'name' => 'Segunda', 'code' => 'T2', 'depends' => $secondDepends]),
        ];

        $this->save($admin, $project, $backlog, $tasks('1:2'))->assertOk();
        $this->assertDatabaseHas('gantt_tasks', ['name' => 'Segunda', 'depends' => '1:2']);

        $response = $this->save($admin, $project, $backlog, $tasks('1', '2'))->assertStatus(422);
        $this->assertStringContainsString('ciclo', $response->json('message'));
    }

    public function test_every_save_bumps_the_revision_and_the_editor_receives_it(): void
    {
        [$admin, $project, $backlog] = $this->scenario();

        $this->actingAs($admin)->getJson(route('projects.gantt.show', [$project, $backlog]))->assertOk()->assertJsonPath('project.revision', 0);

        $this->save($admin, $project, $backlog, [$this->task()])->assertOk()->assertJsonPath('project.revision', 1);
        $this->save($admin, $project, $backlog, [$this->task(['id' => GanttTask::withoutGlobalScopes()->first()->id])])->assertOk()->assertJsonPath('project.revision', 2);
    }

    public function test_a_second_editor_with_a_stale_revision_gets_a_conflict_and_nothing_is_overwritten(): void
    {
        [$admin, $project, $backlog] = $this->scenario();
        $other = User::factory()->create(['company_id' => $admin->company_id, 'role' => 'admin']);

        // Two people open the schedule at revision 0.
        $first = $this->save($admin, $project, $backlog, [$this->task(['name' => 'Da Ana'])], 0)->assertOk();
        $this->assertSame(1, $first->json('project.revision'));

        $second = $this->save($other, $project, $backlog, [$this->task(['name' => 'Do Rui'])], 0);

        $second->assertStatus(409)->assertJsonPath('conflict', true)->assertJsonPath('revision', 1);
        $this->assertStringContainsString('Recarregue', $second->json('message'));
        $this->assertDatabaseHas('gantt_tasks', ['name' => 'Da Ana']);
        $this->assertDatabaseMissing('gantt_tasks', ['name' => 'Do Rui']);
        $this->assertSame(1, (int) $backlog->fresh()->gantt_revision);

        // After reloading (revision 1) the second person can save normally.
        $current = $first->json('project.tasks.0.id');
        $this->save($other, $project, $backlog, [$this->task(['id' => $current, 'name' => 'Do Rui'])], 1)->assertOk();
        $this->assertDatabaseHas('gantt_tasks', ['id' => $current, 'name' => 'Do Rui']);
    }

    public function test_saving_without_a_revision_is_refused(): void
    {
        [$admin, $project, $backlog] = $this->scenario();

        $this->actingAs($admin)->postJson(route('projects.gantt.save', [$project, $backlog]), ['tasks' => [$this->task()]])
            ->assertStatus(422);
        $this->assertSame(0, GanttTask::withoutGlobalScopes()->count());
    }

    public function test_linking_tasks_to_a_backlog_item_invalidates_editors_that_were_open(): void
    {
        [$admin, $project, $backlog] = $this->scenario();
        $this->save($admin, $project, $backlog, [$this->task()], 0)->assertOk();
        $taskId = GanttTask::withoutGlobalScopes()->first()->id;
        app(TenantContext::class)->setCompanyId($admin->company_id);
        $item = ProjectBacklogItem::create([
            'company_id' => $admin->company_id, 'project_id' => $project->id, 'project_backlog_id' => $backlog->id,
            'code' => 'BL-1-01', 'epic' => 'E', 'title' => 'Item', 'priority' => 'P1', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->put(route('projects.backlog.items.gantt-tasks.sync', [$project, $backlog, $item]), ['task_ids' => [$taskId]])->assertRedirect();

        $this->assertSame(2, (int) $backlog->fresh()->gantt_revision);
        $this->save($admin, $project, $backlog, [$this->task(['id' => $taskId])], 1)->assertStatus(409);
    }
}
