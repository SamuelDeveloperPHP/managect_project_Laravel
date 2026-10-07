<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortugueseMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_language_is_brazilian_portuguese(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());
    }

    /** Se o Laravel ganhar uma regra nova de validação e ninguém traduzir, este teste avisa. */
    public function test_every_framework_message_has_a_portuguese_translation(): void
    {
        $framework = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (['auth', 'passwords', 'pagination', 'validation'] as $file) {
            $english = require "{$framework}/{$file}.php";
            $portuguese = require lang_path("pt_BR/{$file}.php");
            $missing = array_diff_key($this->flatten($english), $this->flatten($portuguese));
            unset($missing['custom.attribute-name.rule-name']);

            $this->assertSame([], array_keys($missing), "Faltam traduções em lang/pt_BR/{$file}.php");
        }
    }

    public function test_the_login_error_is_shown_in_portuguese(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'senha-errada'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
    }

    public function test_validation_errors_use_friendly_field_names_in_portuguese(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors([
            'company_name' => 'O campo nome da empresa é obrigatório.',
            'email' => 'O campo e-mail é obrigatório.',
            'password' => 'O campo senha é obrigatório.',
        ]);
    }

    public function test_the_password_reset_e_mail_is_in_portuguese(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $mail = (new ResetPassword('token-de-teste'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Redefinição de senha', $mail->subject);
        foreach (['Olá!', 'Redefinir senha', 'Atenciosamente,', 'Todos os direitos reservados.', 'expira em 60 minutos'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Hello!', 'Regards', 'Reset Password', 'All rights reserved'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    /** @return array<string, string> */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $value) {
            is_array($value)
                ? $flat += $this->flatten($value, "{$prefix}{$key}.")
                : $flat["{$prefix}{$key}"] = $value;
        }

        return $flat;
    }
}
