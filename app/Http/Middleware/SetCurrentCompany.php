<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->is_active || $user->deleted_at !== null) {
            $this->invalidate($request);
            abort(403, 'Sua conta não está ativa para acessar o sistema.');
        }

        if ($user->role === 'master' && ! $user->isPlatformMasterIdentity()) {
            $this->invalidate($request);
            abort(403, 'O acesso Master é reservado à conta da plataforma.');
        }

        $tenantContext = app(TenantContext::class);
        $currentCompany = null;

        if ($user->isPlatformMasterIdentity()) {
            $selectedCompanyId = $request->session()->get('master_company_id');
            if ($selectedCompanyId) {
                $selectedCompany = Company::query()->where('is_active', true)->find($selectedCompanyId);
                if ($selectedCompany) {
                    $tenantContext->setCompanyId((int) $selectedCompany->id);
                    $currentCompany = $selectedCompany;
                } else {
                    $request->session()->forget('master_company_id');
                }
            }

            if ($currentCompany === null) {
                $tenantContext->allowAllCompanies();
            }
        } else {
            $company = $user->company;
            if (! $company || ! $company->is_active || $company->deleted_at !== null) {
                $this->invalidate($request);
                abort(403, 'A empresa está inativa.');
            }

            $tenantContext->setCompanyId((int) $company->id);
            $currentCompany = $company;
        }

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
