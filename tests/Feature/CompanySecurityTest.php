<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\PdfSafetyScanner;
use App\Support\BrazilianTaxDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class CompanySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_only_scoped_users_and_assign_permissions(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $this->actingAs($admin)->post(route('company.users.store'), [
            'name' => 'Analista',
            'email' => 'analista@example.test',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
            'role' => 'user',
            'permissions' => ['can_manage_projects' => true, 'can_view_reports' => false],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'analista@example.test',
            'company_id' => $company->id,
            'role' => 'user',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.users.store', 'company_id' => $company->id, 'outcome' => 'success']);
    }

    public function test_company_admin_cannot_switch_company_settings_with_a_tampered_query(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa do usuário']);
        $otherCompany = Company::factory()->create(['name' => 'Empresa alheia']);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $this->actingAs($admin)->get(route('company.settings.edit', ['company_id' => $otherCompany->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Company/Settings')
                ->where('company.id', $company->id)
                ->where('company.name', 'Empresa do usuário')
                ->where('selectedCompanyId', $company->id));
    }

    public function test_company_admin_can_update_legacy_company_profile_with_valid_document_and_recovery_domain(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa anterior']);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $this->actingAs($admin)->put(route('company.settings.update'), [
            'name' => 'Empresa Atualizada',
            'document_type' => 'CPF',
            'document_number' => '529.982.247-25',
            'domain' => 'empresa.example',
            'admin_recovery_email' => 'recuperacao@empresa.example',
            'secondary_recovery_email' => 'backup@empresa.example',
            'contact_name' => 'Contato responsável',
            'contact_email' => 'contato@example.test',
            'contact_whatsapp' => '+55 11 99999-9999',
            'zip_code' => '01000-000',
            'street' => 'Rua Central',
            'number' => '10',
            'complement' => 'Sala 2',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'sp',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Empresa Atualizada',
            'document_type' => 'CPF',
            'document_number' => '52998224725',
            'domain' => 'empresa.example',
            'state' => 'SP',
        ]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'model.updated']);
    }

    public function test_pdf_scanning_fails_closed_when_security_verifiers_are_not_configured(): void
    {
        config(['security.pdf_validator_binary' => null, 'security.antivirus_binary' => null]);
        $file = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.7\n%%EOF");

        try {
            app(PdfSafetyScanner::class)->assertSafe($file);
            $this->fail('O envio deveria ser recusado sem verificadores configurados.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('temporariamente indisponível', $exception->getMessage());
        }
    }

    public function test_company_admin_cannot_access_the_master_company_list_or_switch_tenant_scope(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $this->actingAs($admin)->get(route('master.companies.index'))->assertForbidden();
        $this->post(route('master.companies.select'), ['company_id' => $company->id])->assertForbidden();
    }

    public function test_regular_user_cannot_open_administration_and_denial_is_audited(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);

        $this->actingAs($user)->get(route('company.users.index'))
            ->assertForbidden()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'company.users.index',
            'status_code' => 403,
            'outcome' => 'denied',
        ]);
    }

    public function test_user_without_project_permission_cannot_create_projects(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'user', 'permissions' => []]);

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Não autorizado',
            'code' => 'BLOCKED',
        ])->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'projects.store',
            'outcome' => 'denied',
        ]);
        $this->assertDatabaseMissing('projects', ['code' => 'BLOCKED']);
    }

    public function test_company_admin_cannot_manage_another_tenant_user(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $companyA->id, 'role' => 'admin']);
        $userB = User::factory()->create(['company_id' => $companyB->id, 'role' => 'user']);

        $this->actingAs($admin)->put(route('company.users.update', $userB), [
            'name' => 'Alterado indevidamente',
            'email' => $userB->email,
            'role' => 'admin',
            'permissions' => [],
        ])->assertNotFound();

        $this->assertSame('user', $userB->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'company_id' => $companyA->id,
            'status_code' => 404,
            'outcome' => 'denied',
        ]);
    }

    public function test_company_admin_cannot_edit_a_master_account(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $master = User::factory()->create(['company_id' => $company->id, 'role' => 'master']);

        $this->actingAs($admin)->put(route('company.users.update', $master), [
            'name' => 'Conta modificada',
            'email' => $master->email,
            'role' => 'user',
            'permissions' => [],
        ])->assertNotFound();

        $this->assertSame('master', $master->fresh()->role);
    }

    public function test_company_admin_cannot_grant_master_role_or_change_own_role(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $member = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);

        $this->actingAs($admin)->put(route('company.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'role' => 'master',
            'permissions' => [],
        ])->assertSessionHasErrors('role');

        $this->put(route('company.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'user',
            'permissions' => [],
        ])->assertSessionHasErrors('role');

        $this->assertSame('user', $member->fresh()->role);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_master_can_select_a_tenant_for_user_administration(): void
    {
        $homeCompany = Company::factory()->create();
        $targetCompany = Company::factory()->create();
        $master = User::factory()->create(['company_id' => $homeCompany->id, 'role' => 'master']);
        $targetUser = User::factory()->create(['company_id' => $targetCompany->id, 'name' => 'Pessoa da empresa B']);

        $this->actingAs($master)
            ->get(route('company.users.index', ['company_id' => $targetCompany->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Company/Users')
                ->where('selectedCompanyId', $targetCompany->id)
                ->has('users', 1));

        $this->assertSame($targetCompany->id, $targetUser->company_id);
    }

    public function test_login_attempt_audit_does_not_store_submitted_password(): void
    {
        $user = User::factory()->create();
        $secret = 'never-store-this-password';

        $this->post('/login', ['email' => $user->email, 'password' => $secret]);

        $this->assertFalse(AuditLog::query()->where('description', 'like', '%'.$secret.'%')->exists());
        $this->assertFalse(AuditLog::query()->where('metadata', 'like', '%'.$secret.'%')->exists());
    }

    public function test_route_activity_without_a_model_resource_has_a_non_null_entity_type(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $this->actingAs($user)->get(route('home'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'action' => 'home',
            'entity_type' => 'route',
            'entity_id' => null,
        ]);
    }

    public function test_audit_events_include_the_server_generated_request_correlation_id(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Request-ID' => 'forged-id'])
            ->get(route('company.users.index'));

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotSame('forged-id', $requestId);
        $this->assertNotEmpty($requestId);
        $audit = AuditLog::query()->where('action', 'company.users.index')->latest('id')->firstOrFail();
        $this->assertSame($requestId, $audit->metadata['request_id']);
    }

    public function test_company_first_registration_is_admin_and_audit_keeps_only_changed_field_names(): void
    {
        $this->post('/register', [
            'name' => 'Responsável',
            'company_name' => 'Empresa CPF',
            'document_type' => 'CPF',
            'document_number' => '529.982.247-25',
            'email' => 'responsavel@example.test',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
        ])->assertRedirect();

        $user = User::query()->where('email', 'responsavel@example.test')->firstOrFail();
        $this->assertSame('admin', $user->role);

        $createdEvent = AuditLog::query()->where('action', 'model.created')->where('entity_type', 'users')->latest('id')->firstOrFail();
        $this->assertContains('role', $createdEvent->metadata['changed_fields']);
        $this->assertNotContains('password', $createdEvent->metadata['changed_fields']);
    }

    public function test_brazilian_tax_documents_are_validated_with_check_digits(): void
    {
        $this->assertTrue(BrazilianTaxDocument::isValid('CPF', '529.982.247-25'));
        $this->assertTrue(BrazilianTaxDocument::isValid('CNPJ', '11.222.333/0001-81'));
        $this->assertFalse(BrazilianTaxDocument::isValid('CPF', '111.111.111-11'));
        $this->assertFalse(BrazilianTaxDocument::isValid('CNPJ', '11.222.333/0001-80'));
    }
}
