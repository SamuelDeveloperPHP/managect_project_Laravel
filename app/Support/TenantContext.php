<?php

namespace App\Support;

final class TenantContext
{
    private ?int $companyId = null;

    private bool $globalAccess = false;

    public function setCompanyId(int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function allowAllCompanies(): void
    {
        $this->globalAccess = true;
    }

    public function hasGlobalAccess(): bool
    {
        return $this->globalAccess;
    }

    public function clear(): void
    {
        $this->companyId = null;
        $this->globalAccess = false;
    }
}
