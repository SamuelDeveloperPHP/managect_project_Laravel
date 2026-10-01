<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_user_and_company_cannot_authenticate(): void
    {
        $inactiveUser = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['email' => $inactiveUser->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $inactiveCompanyUser = User::factory()->create();
        $inactiveCompanyUser->company->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => $inactiveCompanyUser->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_limits_failed_attempts_per_email_across_ips(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($attempt + 10)])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_limits_failed_attempts_per_ip_across_emails(): void
    {
        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.44'])
                ->post('/login', [
                    'email' => 'unknown-'.$attempt.'@example.test',
                    'password' => 'wrong-password',
                ]);
        }

        $user = User::factory()->create();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.44'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_web_responses_include_security_headers_and_server_generated_request_id(): void
    {
        $response = $this->withHeaders(['X-Request-ID' => 'attacker-controlled'])
            ->get('/login');

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotSame('attacker-controlled', $requestId);
        $this->assertTrue(Str::isUuid($requestId));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_https_production_responses_use_nonce_based_content_security_policy(): void
    {
        App::detectEnvironment(fn () => 'production');

        $response = $this->get('https://localhost/login');
        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertStringContainsString("style-src 'self' 'nonce-", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        preg_match("/'nonce-([^']+)'/", $policy, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
    }

    public function test_forwarded_client_ip_is_trusted_only_from_configured_proxies(): void
    {
        config(['security.trusted_proxies' => ['10.0.0.1']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ])->post('/login', ['email' => 'proxy-test@example.test', 'password' => 'wrong-password']);

        $untrustedProxyEvent = AuditLog::query()->where('method', 'POST')->latest('id')->firstOrFail();
        $this->assertSame('198.51.100.10', $untrustedProxyEvent->ip_address);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ])->post('/login', ['email' => 'trusted-proxy-test@example.test', 'password' => 'wrong-password']);

        $trustedProxyEvent = AuditLog::query()->where('method', 'POST')->latest('id')->firstOrFail();
        $this->assertSame('203.0.113.99', $trustedProxyEvent->ip_address);
    }
}
