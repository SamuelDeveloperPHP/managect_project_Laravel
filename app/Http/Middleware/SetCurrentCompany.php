<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companyId = $user?->company_id;

        if (! $companyId || ! $user->is_active || $user->deleted_at !== null) {
            $this->invalidate($request);
            abort(403, 'Sua conta não está ativa para acessar esta empresa.');
        }

        $company = $user->company;
        if (! $company || ! $company->is_active || $company->deleted_at !== null) {
            $this->invalidate($request);
            abort(403, 'A empresa está inativa.');
        }

        $tenantContext = app(TenantContext::class);
        $currentCompany = $company;
        if ($user->hasRole('master')) {
            $selectedCompanyId = $request->session()->get('master_company_id');
            if ($selectedCompanyId) {
                $selectedCompany = Company::query()->find($selectedCompanyId);
                if ($selectedCompany) {
                    $companyId = $selectedCompany->id;
                    $currentCompany = $selectedCompany;
                } else {
                    $request->session()->forget('master_company_id');
                    $selectedCompanyId = null;
                }
            }

            if (! $selectedCompanyId) {
                $tenantContext->allowAllCompanies();
            }
        }
        $tenantContext->setCompanyId((int) $companyId);
        $request->attributes->set('current_company', $currentCompany);

        try {
            return $next($request);
        } finally {
            // Do not leak tenant context across requests in Octane or other long-lived workers.
            $tenantContext->clear();
        }
    }

    private function invalidate(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
