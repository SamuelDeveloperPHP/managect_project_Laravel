<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): Google2FA
    {
        return new Google2FA;
    }

    /** Usuário com o segundo fator já ativo. @return array{0: User, 1: string, 2: list<string>} */
    private function enrolledUser(array $attributes = []): array
    {
        $user = User::factory()->create($attributes + ['password' => Hash::make('uma-senha-longa-123')]);
        $secret = app(TwoFactor::class)->newSecret();
        $codes = app(TwoFactor::class)->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => app(TwoFactor::class)->hashRecoveryCodes($codes),
        ])->save();

        return [$user->fresh(), $secret, $codes];
    }

    private function passwordLogin(User $user, string $password = 'uma-senha-longa-123')
    {
        return $this->post('/login', ['email' => $user->email, 'password' => $password]);
    }

    // ---------- login ----------

    public function test_a_password_alone_does_not_log_in_someone_with_two_factor(): void
    {
        [$user] = $this->enrolledUser();

        $this->passwordLogin($user)->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->last_login_at, 'o último acesso só vale depois do segundo fator');
    }

    public function test_the_correct_authenticator_code_completes_the_login(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $this->passwordLogin($user);

        $this->post('/two-factor-challenge', ['code' => $this->engine()->getCurrentOtp($secret)])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_a_wrong_code_is_refused_and_the_person_stays_logged_out(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $this->passwordLogin($user);
        $wrong = $this->engine()->getCurrentOtp($secret) === '000000' ? '111111' : '000000';

        $this->post('/two-factor-challenge', ['code' => $wrong])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_failed', 'user_id' => $user->id]);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $code = $this->engine()->getCurrentOtp($secret);

        $this->passwordLogin($user);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('dashboard', absolute: false));
        $this->post('/logout');

        // Mesmo código, ainda dentro da janela de 30 s: precisa ser recusado (anti-replay).
        $this->passwordLogin($user);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_challenge_page_is_unavailable_without_a_password_step(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_the_pending_challenge_expires(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $this->passwordLogin($user);

        $this->travel(11)->minutes();

        $this->post('/two-factor-challenge', ['code' => $this->engine()->getCurrentOtp($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_wrong_password_never_reaches_the_second_factor(): void
    {
        [$user] = $this->enrolledUser();

        $this->passwordLogin($user, 'senha-errada-123')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->get('/two-factor-challenge')->assertRedirect(route('login'));
    }

    public function test_repeated_wrong_codes_lock_the_challenge(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $this->passwordLogin($user);
        $wrong = $this->engine()->getCurrentOtp($secret) === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Mesmo agora, com o código CERTO, o bloqueio vale e a verificação pendente é descartada.
        $this->post('/two-factor-challenge', ['code' => $this->engine()->getCurrentOtp($secret)])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_locked']);
    }

    public function test_a_password_check_does_not_end_the_remembered_sessions_of_the_owner(): void
    {
        [$user] = $this->enrolledUser();
        $user->forceFill(['remember_token' => 'token-do-aparelho-confiavel'])->save();

        $this->passwordLogin($user);

        $this->assertSame('token-do-aparelho-confiavel', $user->fresh()->remember_token);
    }

    // ---------- códigos de recuperação ----------

    public function test_a_recovery_code_logs_in_once_and_is_then_consumed(): void
    {
        [$user, , $codes] = $this->enrolledUser();
        $this->passwordLogin($user);

        $this->post('/two-factor-challenge', ['recovery_code' => strtoupper($codes[0])])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertCount(count($codes) - 1, $user->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_recovery_used', 'user_id' => $user->id]);

        $this->post('/logout');
        $this->passwordLogin($user);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_codes_are_stored_only_as_hashes_and_the_secret_encrypted(): void
    {
        [$user, $secret, $codes] = $this->enrolledUser();
        $row = DB::table('users')->where('id', $user->id)->first();

        $this->assertStringNotContainsString($secret, (string) $row->two_factor_secret, 'segredo em texto aberto no banco');
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, (string) $row->two_factor_recovery_codes);
        }
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    // ---------- ativação ----------

    public function test_enrollment_requires_a_valid_code_and_shows_the_recovery_codes_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('two-factor.start'))->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled(), 'gerar o segredo ainda não ativa');

        $this->get(route('profile.edit'))->assertInertia(fn ($page) => $page
            ->where('twoFactor.enabled', false)
            ->where('twoFactor.setup.qr_svg', fn ($svg) => str_contains((string) $svg, '<svg'))
            ->has('twoFactor.setup.secret'));

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->post(route('two-factor.confirm'), ['code' => $this->engine()->getCurrentOtp((string) $user->two_factor_secret)])
            ->assertSessionHas('recovery_codes', fn ($codes) => count($codes) === 8);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_enabled', 'user_id' => $user->id]);
    }

    public function test_recovery_codes_can_be_regenerated_only_with_the_password(): void
    {
        [$user, , $oldCodes] = $this->enrolledUser();
        $this->actingAs($user);

        $this->post(route('two-factor.recovery-codes'), ['password' => 'senha-errada'])->assertSessionHasErrors('password');
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);

        $this->post(route('two-factor.recovery-codes'), ['password' => 'uma-senha-longa-123'])
            ->assertSessionHas('recovery_codes', fn ($codes) => count($codes) === 8 && ! array_intersect($codes, $oldCodes));
    }

    public function test_an_optional_user_can_disable_it_with_the_password(): void
    {
        [$user] = $this->enrolledUser(['role' => 'user']);
        $this->actingAs($user);

        $this->delete(route('two-factor.disable'), ['password' => 'errada'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->delete(route('two-factor.disable'), ['password' => 'uma-senha-longa-123'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_roles_that_require_the_second_factor_cannot_turn_it_off(): void
    {
        config(['security.two_factor_required' => true]);
        [$admin] = $this->enrolledUser(['role' => 'admin']);
        $this->actingAs($admin);

        $this->delete(route('two-factor.disable'), ['password' => 'uma-senha-longa-123'])->assertSessionHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    // ---------- exigência ----------

    public function test_an_admin_without_the_second_factor_is_sent_to_enrollment_when_it_is_required(): void
    {
        config(['security.two_factor_required' => true]);
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
        $this->actingAs($admin);

        $this->get('/dashboard')->assertRedirect(route('profile.edit'));
        $this->get('/projects')->assertRedirect(route('profile.edit'));
        $this->getJson('/projects')->assertStatus(403)->assertJsonPath('two_factor_required', true);
        $this->get(route('profile.edit'))->assertOk()->assertInertia(fn ($page) => $page->where('auth.user.two_factor_required', true));
        $this->post(route('two-factor.start'))->assertRedirect();
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_the_requirement_does_not_touch_regular_users_or_enrolled_admins(): void
    {
        config(['security.two_factor_required' => true]);
        $company = Company::factory()->create();
        $member = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);
        $this->actingAs($member)->get('/projects')->assertOk();

        [$admin] = $this->enrolledUser(['company_id' => $company->id, 'role' => 'admin']);
        $this->actingAs($admin)->get('/projects')->assertOk();
    }

    public function test_the_requirement_is_off_by_default_outside_production(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

        $this->assertFalse(config('security.two_factor_required'));
        $this->actingAs($admin)->get('/projects')->assertOk();
    }

    // ---------- redefinição ----------

    public function test_a_company_admin_can_reset_a_member_but_not_themselves_nor_the_master(): void
    {
        $company = Company::factory()->create();
        [$admin] = $this->enrolledUser(['company_id' => $company->id, 'role' => 'admin']);
        [$member] = $this->enrolledUser(['company_id' => $company->id, 'role' => 'user']);
        $this->actingAs($admin);

        $this->post(route('company.users.two-factor.reset', $member))->assertRedirect();
        $this->assertFalse($member->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_reset', 'entity_id' => $member->id, 'user_id' => $admin->id]);

        $this->post(route('company.users.two-factor.reset', $admin))->assertSessionHasErrors('two_factor');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_member_of_another_company_cannot_be_reset(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        [$admin] = $this->enrolledUser(['company_id' => $company->id, 'role' => 'admin']);
        [$stranger] = $this->enrolledUser(['company_id' => $other->id, 'role' => 'user']);

        $this->actingAs($admin)->post(route('company.users.two-factor.reset', $stranger))->assertNotFound();
        $this->assertTrue($stranger->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_plain_member_cannot_reset_anyone(): void
    {
        $company = Company::factory()->create();
        [$member] = $this->enrolledUser(['company_id' => $company->id, 'role' => 'user']);
        [$colleague] = $this->enrolledUser(['company_id' => $company->id, 'role' => 'user']);

        $this->actingAs($member)->post(route('company.users.two-factor.reset', $colleague))->assertForbidden();
        $this->assertTrue($colleague->fresh()->hasTwoFactorEnabled());
    }

    public function test_the_artisan_command_resets_an_account(): void
    {
        [$user] = $this->enrolledUser();

        $this->artisan('two-factor:reset', ['email' => strtoupper($user->email), '--force' => true])->assertSuccessful();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.two_factor_reset')->count());
        $this->artisan('two-factor:reset', ['email' => 'nao.existe@example.org', '--force' => true])->assertFailed();
    }

    // ---------- TOTP ----------

    public function test_totp_matches_the_rfc_6238_reference_vectors(): void
    {
        // RFC 6238, Apêndice B (SHA-1): segredo ASCII "12345678901234567890" em Base32.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $engine = $this->engine();
        $engine->setOneTimePasswordLength(8);

        $this->assertSame('94287082', $engine->oathTotp($secret, intdiv(59, 30)));
        $this->assertSame('07081804', $engine->oathTotp($secret, intdiv(1111111109, 30)));
        $this->assertSame('14050471', $engine->oathTotp($secret, intdiv(1111111111, 30)));
    }

    public function test_the_first_verification_returns_the_real_time_step_so_replay_protection_works(): void
    {
        $service = app(TwoFactor::class);
        $secret = $service->newSecret();
        $engine = $this->engine();
        $code = $engine->getCurrentOtp($secret);

        $step = $service->verify($secret, $code, null);

        $this->assertSame($engine->getTimestamp(), $step, 'deve ser o passo de tempo real (não 1)');
        $this->assertNull($service->verify($secret, $code, $step), 'o mesmo código não vale duas vezes');
    }

    public function test_the_window_accepts_one_step_of_clock_drift_but_not_more(): void
    {
        $service = app(TwoFactor::class);
        $secret = $service->newSecret();
        $engine = $this->engine();
        $now = $engine->getTimestamp();

        $this->assertNotNull($service->verify($secret, $engine->oathTotp($secret, $now - 1), null), 'um passo para trás');
        $this->assertNotNull($service->verify($secret, $engine->oathTotp($secret, $now + 1), null), 'um passo para frente');
        $this->assertNull($service->verify($secret, $engine->oathTotp($secret, $now - 5), null), 'cinco passos: fora da janela');
        $this->assertNull($service->verify($secret, 'abcdef', null));
        $this->assertNull($service->verify($secret, '12345', null));
    }
}
