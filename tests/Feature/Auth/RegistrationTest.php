<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
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
        $response = $this->post('/register', [
            'name' => 'Test User',
            'company_name' => 'Empresa de Teste',
            'document_type' => 'CPF',
            'document_number' => '529.982.247-25',
            'email' => 'test@example.com',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('companies', ['name' => 'Empresa de Teste']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'admin']);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_a_long_password(): void
    {
        $this->post('/register', [
            'name' => 'Responsável',
            'company_name' => 'Empresa Exemplo',
            'document_type' => 'CNPJ',
            'document_number' => '11.222.333/0001-81',
            'email' => 'responsavel@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'responsavel@example.test']);
    }

    public function test_public_registration_is_closed_outside_development(): void
    {
        App::detectEnvironment(fn () => 'production');

        $this->get('/register')->assertNotFound();
    }
}
