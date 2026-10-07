<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBacklog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados fixos dos testes de interface (Playwright). Só para o banco descartável dos testes E2E:
 * recusa rodar em produção.
 */
class E2eSeeder extends Seeder
{
    public const PASSWORD = 'e2e-senha-forte-123';

    public const ADMIN_EMAIL = 'e2e.admin@example.test';

    public const MEMBER_EMAIL = 'e2e.membro@example.test';

    /** Gestor de projetos da empresa que NÃO participa do projeto: só lê. */
    public const OUTSIDER_EMAIL = 'e2e.fora@example.test';

    /** Convidado que ainda não aceitou a Política de Privacidade e os Termos. */
    public const PENDING_EMAIL = 'e2e.pendente@example.test';

    /** Pessoa cujos dados o administrador apaga no teste de LGPD. */
    public const LEAVER_EMAIL = 'e2e.saiu@example.test';

    public function run(): void
    {
        abort_if(app()->environment('production'), 1, 'E2eSeeder não roda em produção.');

        $company = Company::factory()->create(['name' => 'Empresa E2E']);
        $permissions = ['can_manage_projects' => true, 'can_view_reports' => false];
        $user = fn (string $email, string $role, string $name, bool $acceptedTerms = true): User => User::factory()->create([
            'company_id' => $company->id, 'role' => $role, 'name' => $name, 'email' => $email,
            'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now(), 'is_active' => true,
            'permissions' => $role === 'admin' ? [] : $permissions,
            'terms_version' => $acceptedTerms ? config('privacy.terms_version') : null,
            'terms_accepted_at' => $acceptedTerms ? now() : null,
        ]);
        $admin = $user(self::ADMIN_EMAIL, 'admin', 'Admin E2E');
        $member = $user(self::MEMBER_EMAIL, 'user', 'Membro E2E');
        $user(self::OUTSIDER_EMAIL, 'user', 'Gestor de fora E2E');
        $user(self::PENDING_EMAIL, 'user', 'Convidado E2E', acceptedTerms: false);
        $user(self::LEAVER_EMAIL, 'user', 'Pessoa que saiu E2E');

        app(TenantContext::class)->setCompanyId($company->id);
        $project = Project::create(['name' => 'Projeto E2E', 'code' => 'E2E', 'created_by' => $admin->id]);
        $project->forceFill(['leader_id' => $member->id])->save();
        $project->members()->sync([$member->id]);
        ProjectBacklog::create(['company_id' => $company->id, 'project_id' => $project->id, 'code' => 'BL-01', 'name' => 'Backlog E2E']);
    }
}
