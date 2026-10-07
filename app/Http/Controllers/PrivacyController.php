<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Privacy\UserDataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Páginas públicas (política e termos), ciência dos termos e exportação dos dados do próprio titular. */
class PrivacyController extends Controller
{
    public function policy(): Response
    {
        return Inertia::render('Legal/Privacy', $this->legalProps());
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', $this->legalProps());
    }

    public function acceptPage(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasAcceptedCurrentTerms()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Privacy/AcceptTerms', $this->legalProps());
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'Para continuar, confirme que leu e aceita a Política de Privacidade e os Termos de Uso.']);

        self::recordAcceptance($request->user(), $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Direito de acesso e portabilidade: o titular baixa os próprios dados. */
    public function export(Request $request, UserDataExport $export): HttpResponse
    {
        return self::download($request, $request->user(), $request->user(), $export);
    }

    /** Usado também pelo administrador da empresa, a pedido do titular (ver CompanyAccessController). */
    public static function download(Request $request, User $subject, User $actor, UserDataExport $export): HttpResponse
    {
        AuditLog::query()->create([
            'user_id' => $actor->getKey(), 'company_id' => $subject->company_id, 'action' => 'privacy.data_exported',
            'entity_type' => 'users', 'entity_id' => $subject->getKey(),
            'description' => $actor->is($subject) ? 'Titular baixou os próprios dados' : 'Dados do titular exportados pelo administrador',
            'route_name' => $request->route()?->getName(), 'method' => $request->method(), 'path' => '/'.$request->path(),
            'status_code' => 200, 'outcome' => 'success', 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'created_at' => now(),
        ]);

        $json = json_encode($export->build($subject), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="dados-trilha-'.$subject->getKey().'-'.now()->format('Ymd').'.json"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function recordAcceptance(User $user, Request $request): void
    {
        $user->forceFill(['terms_accepted_at' => now(), 'terms_version' => config('privacy.terms_version')])->save();

        AuditLog::query()->create([
            'user_id' => $user->getKey(), 'company_id' => $user->company_id, 'action' => 'privacy.terms_accepted',
            'entity_type' => 'users', 'entity_id' => $user->getKey(),
            'description' => 'Aceitou a Política de Privacidade e os Termos de Uso (versão '.config('privacy.terms_version').')',
            'route_name' => $request->route()?->getName(), 'method' => $request->method(), 'path' => '/'.$request->path(),
            'status_code' => 200, 'outcome' => 'success', 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function legalProps(): array
    {
        return [
            'legal' => [
                'controller' => config('privacy.controller_name'),
                'contact_email' => config('privacy.contact_email'),
                'dpo_name' => config('privacy.dpo_name'),
                'version' => config('privacy.terms_version'),
                'ip_retention_days' => config('privacy.ip_retention_days'),
                'audit_retention_days' => config('privacy.audit_retention_days'),
                'backup_keep_days' => config('backup.keep_days'),
            ],
        ];
    }
}
