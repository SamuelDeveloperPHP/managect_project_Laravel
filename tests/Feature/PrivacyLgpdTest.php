<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\GanttTask;
use App\Models\GanttTaskAssignment;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivacyLgpdTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'uma-senha-longa-123';

    // ---------- textos públicos e ciência dos termos ----------

    public function test_the_policy_and_the_terms_open_without_login_and_show_the_contact(): void
    {
        config(['privacy.contact_email' => 'privacidade@trilha.example.org', 'privacy.controller_name' => 'NexoCore Tecnologia']);

        $this->get(route('legal.privacy'))->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Privacy')
            ->where('legal.contact_email', 'privacidade@trilha.example.org')->where('legal.controller', 'NexoCore Tecnologia'));
        $this->get(route('legal.terms'))->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Terms'));
    }

    public function test_the_sign_up_requires_accepting_the_terms_and_records_which_version_and_when(): void
    {
        $payload = [
            'name' => 'Ana Titular', 'company_name' => 'Empresa LGPD', 'document_type' => 'CNPJ', 'company_document' => '11222333000181',
            'cpf' => '52998224725', 'email' => 'ana@lgpd.example.org', 'password' => self::PASSWORD.'!', 'password_confirmation' => self::PASSWORD.'!',
        ];

        $this->post('/register', $payload)->assertSessionHasErrors('accept_terms');
        $this->assertDatabaseMissing('users', ['email' => 'ana@lgpd.example.org']);

        $this->post('/register', $payload + ['accept_terms' => true])->assertSessionHasNoErrors();
        $user = User::where('email', 'ana@lgpd.example.org')->firstOrFail();
        $this->assertSame(config('privacy.terms_version'), $user->terms_version);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'privacy.terms_accepted']);
    }

    public function test_when_required_nobody_uses_the_system_before_accepting_the_current_terms(): void
    {
        config(['privacy.terms_required' => true]);
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $this->actingAs($user);

        $this->get('/projects')->assertRedirect(route('terms.accept'));
        $this->getJson('/projects')->assertStatus(403)->assertJsonPath('terms_required', true);
        $this->get(route('terms.accept'))->assertOk();
        $this->get(route('legal.privacy'))->assertOk();
        $this->post('/logout')->assertRedirect('/');

        $this->actingAs($user)->post(route('terms.accept.store'), [])->assertSessionHasErrors('accept');
        $this->post(route('terms.accept.store'), ['accept' => true])->assertRedirect();
        $this->assertTrue($user->fresh()->hasAcceptedCurrentTerms());
        $this->get('/projects')->assertOk();
    }

    public function test_a_new_terms_version_asks_everyone_to_accept_again(): void
    {
        config(['privacy.terms_required' => true]);
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'terms_version' => config('privacy.terms_version'), 'terms_accepted_at' => now()]);
        $this->actingAs($user)->get('/projects')->assertOk();

        config(['privacy.terms_version' => '2099-01']);
        $this->get('/projects')->assertRedirect(route('terms.accept'));
    }

    public function test_the_requirement_is_off_by_default_outside_production(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->assertFalse(config('privacy.terms_required'));
        $this->actingAs($user)->get('/projects')->assertOk();
    }

    // ---------- direito de acesso e portabilidade ----------

    public function test_the_person_downloads_their_own_data_without_secrets_or_other_peoples_data(): void
    {
        [$company, $admin, $member, $project] = $this->team();
        $project->members()->attach($member->id);
        $task = $this->task($project, 'Tarefa da Bia');
        GanttTaskAssignment::create(['gantt_task_id' => $task->id, 'company_id' => $company->id, 'user_id' => $member->id, 'role' => 'R', 'effort' => 3600]);
        $member->forceFill(['two_factor_secret' => 'SEGREDO-NAO-PODE-VAZAR'])->save();
        AuditLog::query()->create(['user_id' => $member->id, 'company_id' => $company->id, 'action' => 'auth.login', 'method' => 'POST', 'path' => '/login', 'outcome' => 'success', 'ip_address' => '203.0.113.7', 'created_at' => now()]);
        AuditLog::query()->create(['user_id' => $admin->id, 'company_id' => $company->id, 'action' => 'admin.only', 'method' => 'POST', 'path' => '/x', 'outcome' => 'success', 'created_at' => now()]);

        $response = $this->actingAs($member)->get(route('profile.data-export'))->assertOk();
        $this->assertStringContainsString('attachment; filename="dados-trilha-'.$member->id, $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($member->email, $data['titular']['email']);
        $this->assertSame('Projeto LGPD', $data['projetos'][0]['nome']);
        $this->assertSame('Tarefa da Bia', $data['tarefas_atribuidas'][0]['tarefa']);
        $this->assertContains('203.0.113.7', array_column($data['registros_de_atividade'], 'ip'));
        $this->assertNotContains('admin.only', array_column($data['registros_de_atividade'], 'acao'));

        $raw = $response->getContent();
        $this->assertStringNotContainsString('SEGREDO-NAO-PODE-VAZAR', $raw);
        $this->assertStringNotContainsString($member->password, $raw);
        $this->assertStringNotContainsString($admin->email, $raw);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $member->id, 'action' => 'privacy.data_exported']);
    }

    public function test_an_admin_exports_a_member_of_the_same_company_only(): void
    {
        [$company, $admin, $member] = $this->team();
        [, , $stranger] = $this->team('outra');

        $this->actingAs($admin)->get(route('company.users.data-export', $member->id))->assertOk()
            ->assertJsonPath('titular.email', $member->email);
        $this->get(route('company.users.data-export', $stranger->id))->assertNotFound();
        $this->actingAs($member)->get(route('company.users.data-export', $admin->id))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'privacy.data_exported', 'entity_id' => $member->id]);
    }

    // ---------- direito de eliminação / anonimização ----------

    public function test_deleting_the_account_erases_the_personal_data_but_keeps_the_history(): void
    {
        Storage::fake('public');
        [$company, $admin, $member, $project] = $this->team();
        Storage::disk('public')->put('profile-photos/bia.jpg', 'foto');
        $member->forceFill(['profile_photo_path' => 'profile-photos/bia.jpg', 'last_login_at' => now(), 'two_factor_secret' => 'X', 'two_factor_confirmed_at' => now()])->save();
        $project->members()->attach($member->id);
        $project->forceFill(['leader_id' => $member->id])->save();
        $task = $this->task($project, 'Tarefa mantida');
        GanttTaskAssignment::create(['gantt_task_id' => $task->id, 'company_id' => $company->id, 'user_id' => $member->id, 'role' => 'R', 'effort' => 0]);
        DB::table('sessions')->insert(['id' => 'sess-bia', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $member->email, 'token' => 'x', 'created_at' => now()]);
        $oldEmail = $member->email;
        AuditLog::query()->create(['user_id' => $admin->id, 'company_id' => $company->id, 'action' => 'auth.two_factor_reset', 'description' => 'Segundo fator redefinido para '.$oldEmail, 'method' => 'POST', 'path' => '/x', 'outcome' => 'success', 'created_at' => now()]);

        $this->actingAs($member)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $row = User::withTrashed()->findOrFail($member->id);
        $this->assertNotNull($row->deleted_at);
        $this->assertNotNull($row->anonymized_at);
        $this->assertSame('Usuário removido', $row->name);
        $this->assertSame("anonimizado-{$member->id}@anonimizado.invalid", $row->email);
        $this->assertNull($row->cpf);
        $this->assertNull($row->profile_photo_path);
        $this->assertNull($row->two_factor_secret);
        $this->assertFalse($row->is_active);
        $this->assertFalse(Hash::check('password', $row->password));
        Storage::disk('public')->assertMissing('profile-photos/bia.jpg');

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-bia']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertDatabaseMissing('project_members', ['user_id' => $member->id]);
        $this->assertNull($project->fresh()->leader_id);
        $this->assertSame(0, AuditLog::query()->where('description', 'like', '%'.$oldEmail.'%')->count(), 'o e-mail antigo não pode sobrar nos registros');
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_reset', 'description' => 'Segundo fator redefinido para (e-mail removido)']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.user_anonymized', 'entity_id' => $member->id]);

        // O histórico do projeto continua: a tarefa e a atribuição existem, agora sem identificar ninguém.
        $this->assertDatabaseHas('gantt_task_assignments', ['gantt_task_id' => $task->id, 'user_id' => $member->id]);
        $this->post('/login', ['email' => $oldEmail, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_the_last_administrator_cannot_erase_their_own_account(): void
    {
        [, $admin] = $this->team();

        $this->actingAs($admin)->delete('/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertNull(User::withTrashed()->find($admin->id)->anonymized_at);
    }

    public function test_an_admin_erases_a_members_data_with_their_own_password(): void
    {
        [, $admin, $member] = $this->team();

        $this->actingAs($admin)->post(route('company.users.anonymize', $member->id), ['password' => 'errada'])->assertSessionHasErrors('password');
        $this->assertNull($member->fresh()->anonymized_at);

        $this->post(route('company.users.anonymize', $member->id), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNotNull(User::withTrashed()->find($member->id)->anonymized_at);
    }

    public function test_an_admin_cannot_erase_themselves_another_admin_or_someone_from_another_company(): void
    {
        [$company, $admin] = $this->team();
        [, , $stranger] = $this->team('outra');
        $this->actingAs($admin);

        $this->post(route('company.users.anonymize', $admin->id), ['password' => 'password'])->assertSessionHasErrors('password');
        $this->post(route('company.users.anonymize', $stranger->id), ['password' => 'password'])->assertNotFound();
        $this->assertNull($stranger->fresh()->anonymized_at);
    }

    public function test_a_plain_member_cannot_erase_anyone(): void
    {
        [$company, , $member] = $this->team();
        $colleague = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);

        $this->actingAs($member)->post(route('company.users.anonymize', $colleague->id), ['password' => 'password'])->assertForbidden();
    }

    public function test_the_artisan_command_anonymizes_and_refuses_the_master(): void
    {
        [, , $member] = $this->team();
        Company::query()->find(User::PLATFORM_MASTER_COMPANY_ID) ?? Company::factory()->create(['id' => User::PLATFORM_MASTER_COMPANY_ID]);
        $master = User::factory()->create(['role' => 'master', 'email' => User::platformMasterEmail(), 'company_id' => User::PLATFORM_MASTER_COMPANY_ID]);

        $this->artisan('privacy:anonymize', ['email' => mb_strtoupper($member->email), '--force' => true])->assertSuccessful();
        $this->assertNotNull(User::withTrashed()->find($member->id)->anonymized_at);
        $this->artisan('privacy:anonymize', ['email' => "anonimizado-{$member->id}@anonimizado.invalid", '--force' => true])->assertSuccessful();

        $this->artisan('privacy:anonymize', ['email' => $master->email, '--force' => true])->assertFailed();
        $this->assertNull($master->fresh()->anonymized_at);
    }

    // ---------- retenção ----------

    public function test_pruning_strips_ip_after_the_retention_and_deletes_expired_records(): void
    {
        config(['privacy.ip_retention_days' => 365, 'privacy.audit_retention_days' => 730]);
        $log = fn (string $action, int $daysAgo) => AuditLog::query()->create([
            'action' => $action, 'method' => 'GET', 'path' => '/x', 'outcome' => 'success', 'ip_address' => '198.51.100.9',
            'user_agent' => 'Navegador', 'created_at' => now()->subDays($daysAgo),
        ]);
        $recent = $log('recente', 10);
        $old = $log('antigo', 400);
        $expired = $log('vencido', 800);

        $this->artisan('privacy:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('198.51.100.9', $old->fresh()->ip_address);
        $this->assertNotNull($expired->fresh());

        $this->artisan('privacy:prune')->assertSuccessful();
        $this->assertSame('198.51.100.9', $recent->fresh()->ip_address);
        $this->assertNull($old->fresh()->ip_address);
        $this->assertNull($old->fresh()->user_agent);
        $this->assertSame('antigo', $old->fresh()->action, 'o registro fica, só perde o IP');
        $this->assertNull(AuditLog::query()->find($expired->id));
    }

    public function test_pruning_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('privacy:prune')->assertSuccessful();
    }

    // ---------- auxiliares ----------

    /** @return array{0: Company, 1: User, 2: User, 3: Project} */
    private function team(string $slug = 'lgpd'): array
    {
        $company = Company::create(['slug' => $slug, 'name' => 'Empresa '.$slug]);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'name' => 'Admin '.$slug, 'cpf' => $slug === 'lgpd' ? '52998224725' : '11144477735']);
        $member = User::factory()->create(['company_id' => $company->id, 'role' => 'user', 'name' => 'Bia '.$slug, 'cpf' => $slug === 'lgpd' ? '39053344705' : '12345678909']);
        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto LGPD', 'code' => strtoupper($slug), 'created_by' => $admin->id]);

        return [$company, $admin, $member, $project];
    }

    private function task(Project $project, string $name): GanttTask
    {
        $backlog = ProjectBacklog::create(['company_id' => $project->company_id, 'project_id' => $project->id, 'code' => 'BL-01', 'name' => 'Backlog']);

        return GanttTask::withoutGlobalScopes()->create([
            'company_id' => $project->company_id, 'project_id' => $project->id, 'project_backlog_id' => $backlog->id, 'name' => $name, 'code' => 'T1', 'level' => 0,
            'status' => 'STATUS_ACTIVE', 'progress' => 0, 'start_at' => now(), 'end_at' => now()->addDay(), 'duration' => 2,
        ]);
    }
}
