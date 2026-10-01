<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
        $response->assertSessionHas('status', 'Se as informações corresponderem a uma conta ativa, enviaremos as instruções de recuperação para o e-mail cadastrado.');
    }

    public function test_password_reset_response_does_not_disclose_unknown_email(): void
    {
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'unknown@example.test',
        ]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se as informações corresponderem a uma conta ativa, enviaremos as instruções de recuperação para o e-mail cadastrado.');
    }

    public function test_password_recovery_requests_are_limited_per_email_across_ips(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/forgot-password')
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$attempt])
                ->post('/forgot-password', ['email' => 'unknown@example.test'])
                ->assertRedirect('/forgot-password');
        }

        $this->from('/forgot-password')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])
            ->post('/forgot-password', ['email' => 'unknown@example.test'])
            ->assertTooManyRequests();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $rememberTokenBeforeReset = $user->remember_token;

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $rememberTokenBeforeReset) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-long-secure-password',
                'password_confirmation' => 'new-long-secure-password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertNotSame($rememberTokenBeforeReset, $user->fresh()->remember_token);

            return true;
        });
    }
}
