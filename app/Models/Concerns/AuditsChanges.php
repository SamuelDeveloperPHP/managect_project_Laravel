<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuditsChanges
{
    protected static function bootAuditsChanges(): void
    {
        static::created(function (Model $model): void {
            static::writeModelAudit($model, 'created', array_keys($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            static::writeModelAudit($model, 'updated', array_keys($model->getChanges()));
        });

        static::deleted(function (Model $model): void {
            static::writeModelAudit($model, 'deleted', ['deleted_at']);
        });
    }

    private static function writeModelAudit(Model $model, string $event, array $fields): void
    {
        $safeFields = array_values(array_intersect($fields, [
            'name', 'email', 'role', 'permissions', 'is_active', 'company_id', 'document_type',
            'document_number', 'code', 'title', 'epic', 'description', 'priority', 'status',
            'release', 'points', 'created_by', 'deleted_at', 'client', 'deadline', 'progress',
            'domain', 'zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
            'logo_path', 'contact_name', 'contact_email', 'contact_whatsapp', 'admin_recovery_email', 'secondary_recovery_email',
        ]));
        $actor = Auth::user();
        $companyId = $model->getAttribute('company_id');

        if ($companyId === null && $model->getTable() === 'companies') {
            $companyId = $model->getKey();
        }

        AuditLog::query()->create([
            'user_id' => $actor?->getAuthIdentifier(),
            'company_id' => $companyId,
            'action' => 'model.'.$event,
            'entity_type' => mb_substr($model->getTable(), 0, 80),
            'entity_id' => $model->getKey(),
            'description' => mb_substr(ucfirst($event).' '.$model->getTable(), 0, 500),
            'route_name' => request()->route()?->getName(),
            'method' => request()->method(),
            'path' => request()->route()?->uri() ? '/'.request()->route()->uri() : '(application event)',
            'status_code' => null,
            'outcome' => 'success',
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'metadata' => [
                'changed_fields' => $safeFields,
                'actor_role' => $actor?->role,
                'actor_id' => $actor?->getAuthIdentifier(),
                'request_id' => request()->attributes->get('request_id'),
            ],
            'created_at' => now(),
        ]);
    }
}
