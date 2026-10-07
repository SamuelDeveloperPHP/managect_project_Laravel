<?php

namespace App\Support\Privacy;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Direito de eliminação/anonimização (LGPD art. 18, IV e VI).
 *
 * A linha do usuário continua existindo (o histórico do projeto, as tarefas e a auditoria apontam para ela), mas
 * todo dado que identifica a pessoa é apagado: nome, e-mail, CPF, foto, senha, segundo fator, sessões e vínculos.
 * Os registros de atividade ficam pelo prazo legal (ver `privacy:prune`), sem o e-mail da pessoa nas descrições.
 */
class UserAnonymizer
{
    public function anonymize(User $user, ?User $actor = null, string $origin = 'titular'): void
    {
        if ($user->role === 'master') {
            throw new InvalidArgumentException('A conta Master não pode ser anonimizada.');
        }
        if ($user->anonymized_at !== null) {
            return;
        }

        $oldEmail = (string) $user->email;
        $photo = $user->profile_photo_path;
        $id = $user->getKey();

        DB::transaction(function () use ($user, $oldEmail, $id, $actor, $origin): void {
            $user->forceFill([
                'name' => 'Usuário removido',
                'email' => "anonimizado-{$id}@anonimizado.invalid",
                'cpf' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'permissions' => [],
                'is_active' => false,
                'profile_photo_path' => null,
                'last_login_at' => null,
                'email_verified_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_step' => null,
                'anonymized_at' => now(),
            ])->save();

            // Sessões abertas e vínculos pessoais.
            $sessions = (string) config('session.table', 'sessions');
            if (Schema::hasTable($sessions)) {
                DB::table($sessions)->where('user_id', $id)->delete();
            }
            DB::table('project_members')->where('user_id', $id)->delete();
            DB::table('projects')->where('leader_id', $id)->update(['leader_id' => null]);
            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

            // O e-mail antigo não pode sobrar nas descrições dos registros de atividade.
            if ($oldEmail !== '') {
                DB::update(
                    'UPDATE audit_logs SET description = REPLACE(description, ?, ?) WHERE description LIKE ?',
                    [$oldEmail, '(e-mail removido)', '%'.$oldEmail.'%'],
                );
            }

            AuditLog::query()->create([
                'user_id' => $actor?->getKey(), 'company_id' => $user->company_id, 'action' => 'privacy.user_anonymized',
                'entity_type' => 'users', 'entity_id' => $id,
                'description' => "Dados pessoais anonimizados (origem: {$origin})",
                'route_name' => request()->route()?->getName(), 'method' => request()->method() ?: 'CLI',
                'path' => request()->route()?->uri() ? '/'.request()->route()->uri() : '(application event)',
                'status_code' => null, 'outcome' => 'success',
                'ip_address' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);

            $user->delete();
        });

        if ($photo) {
            Storage::disk('public')->delete($photo);
        }
    }
}
