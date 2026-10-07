<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', ['accept_terms' => true,
            'name' => 'Test User',
            'company_name' => 'Empresa de Teste',
            'company_cnpj' => '11.222.333/0001-81',
            'cpf' => '529.982.247-25',
            'email' => 'test@example.com',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('companies', ['name' => 'Empresa de Teste', 'document_type' => 'CNPJ', 'document_number' => '11222333000181']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'cpf' => '52998224725', 'role' => 'admin']);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_rejects_secondary_recovery_email_equal_to_administrator_email(): void
    {
        $this->post('/register', ['accept_terms' => true, 'name' => 'Test User', 'company_name' => 'Empresa de Teste', 'company_cnpj' => '11.222.333/0001-81', 'cpf' => '529.982.247-25', 'email' => 'test@example.com', 'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password', 'secondary_recovery_email' => 'test@example.com'])
            ->assertSessionHasErrors('secondary_recovery_email');
        $this->assertGuest();
    }

    public function test_registration_stores_distinct_secondary_recovery_email(): void
    {
        $this->post('/register', ['accept_terms' => true, 'name' => 'Test User', 'company_name' => 'Empresa de Teste', 'company_cnpj' => '11.222.333/0001-81', 'cpf' => '529.982.247-25', 'email' => 'test@example.com', 'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password', 'secondary_recovery_email' => 'backup@example.org'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['document_number' => '11222333000181', 'secondary_recovery_email' => 'backup@example.org']);
    }

    public function test_admin_password_reset_goes_to_both_recovery_emails(): void
    {
        Notification::fake();
        $this->post('/register', ['accept_terms' => true, 'name' => 'Test User', 'company_name' => 'Empresa de Teste', 'company_cnpj' => '11.222.333/0001-81', 'cpf' => '529.982.247-25', 'email' => 'test@example.com', 'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password', 'secondary_recovery_email' => 'backup@example.org']);
        auth()->logout();
        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->post('/forgot-password', ['email' => 'test@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            return $user->routeNotificationForMail($n) === ['test@example.com', 'backup@example.org'];
        });
    }

    public function test_registration_requires_a_long_password(): void
    {
        $this->post('/register', ['accept_terms' => true,
            'name' => 'Responsável',
            'company_name' => 'Empresa Exemplo',
            'company_cnpj' => '11.222.333/0001-81',
            'cpf' => '529.982.247-25',
            'email' => 'responsavel@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'responsavel@example.test']);
    }

    public function test_public_registration_is_available_in_production(): void
    {
        App::detectEnvironment(fn () => 'production');

        $this->get('/register')->assertOk();
    }

    public function test_registration_accepts_a_cpf_as_the_company_document(): void
    {
        $this->post('/register', ['accept_terms' => true,
            'name' => 'Maria Autonoma', 'company_name' => 'Maria Consultoria',
            'document_type' => 'CPF', 'company_document' => '529.982.247-25', 'cpf' => '529.982.247-25',
            'email' => 'maria@example.com', 'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('companies', ['name' => 'Maria Consultoria', 'document_type' => 'CPF', 'document_number' => '52998224725', 'cnpj' => null]);
    }

    public function test_registration_rejects_a_cnpj_typed_as_cpf(): void
    {
        $this->post('/register', ['accept_terms' => true,
            'name' => 'Maria', 'company_name' => 'Maria Consultoria',
            'document_type' => 'CPF', 'company_document' => '11.222.333/0001-81', 'cpf' => '529.982.247-25',
            'email' => 'maria@example.com', 'password' => 'long-secure-password', 'password_confirmation' => 'long-secure-password',
        ])->assertSessionHasErrors('company_document');
        $this->assertGuest();
    }
}
