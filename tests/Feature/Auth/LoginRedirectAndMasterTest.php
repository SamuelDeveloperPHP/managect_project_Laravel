<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectAndMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_login_returns_to_the_login_form_even_when_the_last_full_page_was_the_home(): void
    {
        $user = User::factory()->create();

        // O Inertia navega de "/" para "/login" sem gravar a página anterior na sessão: ela continua sendo a home.
        $response = $this->withSession(['_previous' => ['url' => url('/')]])
            ->post('/login', ['email' => $user->email, 'password' => 'senha-errada-123']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_every_login_rejection_goes_back_to_the_login_form(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);

        $this->withSession(['_previous' => ['url' => url('/')]])
            ->post('/login', ['email' => $inactive->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->withSession(['_previous' => ['url' => url('/')]])
            ->post('/login', ['email' => 'ninguem@example.org', 'password' => 'qualquer-senha-123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_a_master_role_on_an_e_mail_that_is_not_the_platform_identity_cannot_log_in(): void
    {
        $company = Company::factory()->create(['id' => 2]);
        // Situação real encontrada em um banco de desenvolvimento: papel "master" gravado direto no banco, com outro e-mail.
        DB::table('users')->insert([
            'name' => 'Administrador Master', 'email' => 'samueltgq@gmail.com', 'password' => Hash::make('uma-senha-longa-123'),
            'role' => 'master', 'is_active' => true, 'company_id' => $company->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post('/login', ['email' => 'samueltgq@gmail.com', 'password' => 'uma-senha-longa-123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_platform_master_e_mail_can_be_configured_and_then_logs_in_normally(): void
    {
        config(['platform.master_email' => 'Samueltgq@Gmail.com']);
        Company::factory()->create(['id' => 2]);
        User::factory()->create(['email' => 'samueltgq@gmail.com', 'role' => 'master', 'password' => Hash::make('uma-senha-longa-123')]);

        $this->assertSame('samueltgq@gmail.com', User::platformMasterEmail());

        $this->post('/login', ['email' => 'samueltgq@gmail.com', 'password' => 'uma-senha-longa-123'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_only_one_identity_may_hold_the_master_role(): void
    {
        config(['platform.master_email' => 'samueltgq@gmail.com']);
        Company::factory()->create(['id' => 2]);

        $this->expectException(\LogicException::class);
        User::factory()->create(['email' => 'outra.pessoa@example.org', 'role' => 'master']);
    }
}
