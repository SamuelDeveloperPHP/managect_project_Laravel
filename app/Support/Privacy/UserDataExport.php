<?php

namespace App\Support\Privacy;

use App\Models\AuditLog;
use App\Models\GanttTaskAssignment;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;

/**
 * Direito de acesso e portabilidade (LGPD art. 18, II e V): tudo o que o sistema guarda sobre UMA pessoa, em JSON.
 * Nunca inclui segredos (senha, segredo e códigos do 2FA) nem dados de outras pessoas.
 */
class UserDataExport
{
    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $companyId = $user->company_id;

        return [
            'gerado_em' => now()->toIso8601String(),
            'sobre_este_arquivo' => 'Dados pessoais da sua conta no Trilha+ (LGPD, art. 18). Não contém senhas nem segredos de segurança.',
            'titular' => [
                'id' => $user->id,
                'nome' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'perfil' => $user->role,
                'permissoes' => $user->permissions ?? [],
                'conta_ativa' => (bool) $user->is_active,
                'email_verificado_em' => $user->email_verified_at?->toIso8601String(),
                'criada_em' => $user->created_at?->toIso8601String(),
                'ultimo_acesso_em' => $user->last_login_at?->toIso8601String(),
                'possui_foto_de_perfil' => $user->profile_photo_path !== null,
            ],
            'empresa' => $user->company ? [
                'nome' => $user->company->name,
                'tipo_documento' => $user->company->document_type,
            ] : null,
            'ciencia_dos_termos' => [
                'versao' => $user->terms_version,
                'aceita_em' => $user->terms_accepted_at?->toIso8601String(),
            ],
            'seguranca' => [
                'verificacao_em_duas_etapas_ativa' => $user->hasTwoFactorEnabled(),
                'codigos_de_recuperacao_restantes' => $user->hasTwoFactorEnabled() ? count((array) $user->two_factor_recovery_codes) : 0,
            ],
            'projetos' => $this->projects($user, $companyId),
            'tarefas_atribuidas' => $this->assignments($user, $companyId),
            'arquivos_enviados' => $this->uploads($user, $companyId),
            'registros_de_atividade' => $this->activity($user),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function projects(User $user, ?int $companyId): array
    {
        $memberOf = $user->getKey();

        return Project::withoutGlobalScopes()->where('company_id', $companyId)
            ->where(fn ($query) => $query->where('leader_id', $memberOf)->orWhere('created_by', $memberOf)
                ->orWhereIn('id', fn ($sub) => $sub->select('project_id')->from('project_members')->where('user_id', $memberOf)))
            ->orderBy('name')->get(['id', 'name', 'code', 'leader_id', 'created_by'])
            ->map(function (Project $project) use ($user): array {
                $roles = [];
                if ((int) $project->leader_id === $user->getKey()) {
                    $roles[] = 'líder';
                }
                if ((int) $project->created_by === $user->getKey()) {
                    $roles[] = 'criador';
                }
                if (! $roles || $project->members()->whereKey($user->getKey())->exists()) {
                    $roles[] = 'membro';
                }

                return ['id' => $project->id, 'nome' => $project->name, 'codigo' => $project->code, 'papeis' => array_values(array_unique($roles))];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function assignments(User $user, ?int $companyId): array
    {
        return GanttTaskAssignment::query()->where('user_id', $user->getKey())->where('company_id', $companyId)
            ->with(['task' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'project_id', 'name'])])
            ->get()->map(fn (GanttTaskAssignment $assignment): array => [
                'tarefa' => $assignment->task?->name,
                'projeto_id' => $assignment->task?->project_id,
                'funcao' => $assignment->role,
                'esforco' => $assignment->effort,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function uploads(User $user, ?int $companyId): array
    {
        return ProjectAttachment::withoutGlobalScopes()->where('uploaded_by', $user->getKey())
            ->whereIn('project_id', Project::withoutGlobalScopes()->where('company_id', $companyId)->select('id'))
            ->get()->map(fn (ProjectAttachment $file): array => [
                'arquivo' => $file->original_name,
                'projeto_id' => $file->project_id,
                'tamanho_bytes' => $file->size_bytes,
                'enviado_em' => $file->created_at?->toIso8601String(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function activity(User $user): array
    {
        return AuditLog::query()->where('user_id', $user->getKey())->latest('created_at')->latest('id')
            ->limit((int) config('privacy.export_audit_limit'))
            ->get(['created_at', 'action', 'description', 'outcome', 'ip_address', 'user_agent'])
            ->map(fn (AuditLog $log): array => [
                'data' => $log->created_at?->toIso8601String(),
                'acao' => $log->action,
                'descricao' => $log->description,
                'resultado' => $log->outcome,
                'ip' => $log->ip_address,
                'navegador' => $log->user_agent,
            ])->all();
    }
}
