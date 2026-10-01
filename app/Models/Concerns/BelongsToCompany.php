<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->hasGlobalAccess()) {
                return;
            }

            $companyId = $context->companyId();
            $builder->where($builder->getModel()->getTable().'.company_id', $companyId ?? 0);
        });

        static::creating(function (Model $model): void {
            $companyId = app(TenantContext::class)->companyId();

            if ($companyId === null) {
                throw new \LogicException('A empresa ativa é obrigatória para gravar dados.');
            }

            $model->setAttribute('company_id', $companyId);
        });
    }
}
