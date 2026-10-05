<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\BrazilianTaxDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class MasterCompaniesController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'document_number' => preg_replace('/\D+/', '', (string) $request->input('document_number')),
            'domain' => filled($request->input('domain')) ? mb_strtolower(trim((string) $request->input('domain'))) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'document_type' => ['required', Rule::in(['CNPJ', 'CPF'])],
            'document_number' => ['required', 'string', 'max:14', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (! BrazilianTaxDocument::isValid((string) $request->input('document_type'), (string) $value)) {
                    $fail('Informe um CNPJ ou CPF válido.');
                }
            }, Rule::unique('companies', 'document_number')->where('document_type', $request->input('document_type'))],
            'domain' => ['nullable', 'string', 'max:190', 'regex:/^(?=.{1,190}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', Rule::unique('companies', 'domain')],
        ]);

        $baseSlug = Str::slug($data['name']) ?: 'empresa';
        $slug = $baseSlug;
        $suffix = 2;
        while (DB::table('companies')->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $company = Company::query()->create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'],
            'cnpj' => $data['document_type'] === 'CNPJ' ? $data['document_number'] : null,
            'domain' => $data['domain'] ?? null,
            'is_active' => true,
        ]);
        $request->session()->put('master_company_id', $company->id);

        return redirect()->route('master.companies.index')->with('success', 'Empresa cadastrada e ativada. Ela já está selecionada; agora cadastre o primeiro Administrador na área Equipe.');
    }

    public function index(Request $request): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($data['q'] ?? ''));
        $query = Company::query()->orderBy('name');
        if ($term !== '') {
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            $query->where(function ($companies) use ($term, $digits): void {
                $companies->where('name', 'like', '%'.$term.'%')->orWhere('domain', 'like', '%'.$term.'%');
                if ($digits !== '') {
                    $companies->orWhere('document_number', 'like', '%'.$digits.'%')->orWhere('cnpj', 'like', '%'.$digits.'%');
                }
            });
        }
        $companies = $query->get(['id', 'name', 'document_type', 'document_number', 'cnpj', 'domain', 'is_active']);
        $ids = $companies->modelKeys();

        $users = DB::table('users')->whereIn('company_id', $ids)->whereNull('deleted_at')->where('role', '!=', 'master')
            ->select('company_id', DB::raw('COUNT(*) as total_users'), DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users'))
            ->groupBy('company_id')->get()->keyBy('company_id');
        $projects = DB::table('projects')->whereIn('company_id', $ids)->whereNull('deleted_at')
            ->select('company_id', DB::raw('COUNT(*) as total_projects'), DB::raw("SUM(CASE WHEN status IN ('active','in_progress','STATUS_ACTIVE') THEN 1 ELSE 0 END) as active_projects"))
            ->groupBy('company_id')->get()->keyBy('company_id');
        $events = DB::table('audit_logs')->whereIn('company_id', $ids)->where('created_at', '>=', now()->subDays(30))
            ->select('company_id', DB::raw('COUNT(*) as events_30d'), DB::raw('MAX(created_at) as last_activity_at'))
            ->groupBy('company_id')->get()->keyBy('company_id');

        $onlineUsers = collect();
        $trackingOnline = config('session.driver') === 'database'
            && DB::getSchemaBuilder()->hasTable(config('session.table', 'sessions'));
        if ($trackingOnline && count($ids) > 0) {
            $onlineUsers = DB::table(config('session.table', 'sessions').' as s')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->whereIn('u.company_id', $ids)->where('u.is_active', true)->where('u.role', '!=', 'master')
                ->whereNull('u.deleted_at')->where('s.last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)
                ->select('u.company_id', DB::raw('COUNT(DISTINCT u.id) as online_users'))->groupBy('u.company_id')->get()->keyBy('company_id');
        }

        $selectedCompanyId = $request->session()->get('master_company_id');
        $rows = $companies->map(function (Company $company) use ($users, $projects, $events, $onlineUsers, $trackingOnline, $selectedCompanyId): array {
            $userCounts = $users->get($company->id);
            $projectCounts = $projects->get($company->id);
            $activity = $events->get($company->id);
            $totalUsers = (int) ($userCounts->total_users ?? 0);
            $activeUsers = (int) ($userCounts->active_users ?? 0);
            $online = (int) ($onlineUsers->get($company->id)->online_users ?? 0);
            $document = preg_replace('/\D+/', '', (string) ($company->document_number ?: $company->cnpj));
            $maskedDocument = $document ? str_repeat('•', max(0, strlen($document) - 4)).substr($document, -4) : null;

            return [
                'id' => $company->id,
                'name' => $company->name,
                'document_type' => $company->document_type ?: ($company->cnpj ? 'CNPJ' : null),
                'document_masked' => $maskedDocument,
                'domain' => $company->domain,
                'is_active' => (bool) $company->is_active,
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => max(0, $totalUsers - $activeUsers),
                'online_users' => $trackingOnline ? $online : null,
                'offline_users' => $trackingOnline ? max(0, $activeUsers - $online) : null,
                'total_projects' => (int) ($projectCounts->total_projects ?? 0),
                'active_projects' => (int) ($projectCounts->active_projects ?? 0),
                'events_30d' => (int) ($activity->events_30d ?? 0),
                'last_activity_at' => $activity->last_activity_at,
                'selected' => (int) $selectedCompanyId === $company->id,
            ];
        })->values();

        return Inertia::render('Master/Companies', [
            'companies' => $rows,
            'filters' => ['q' => $term],
            'selectedCompanyId' => $selectedCompanyId ? (int) $selectedCompanyId : null,
            'sessionTrackingAvailable' => $trackingOnline,
            'success' => $request->session()->get('success'),
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
        ]);

        if (empty($data['company_id'])) {
            $request->session()->forget('master_company_id');
            return redirect()->route('dashboard')->with('success', 'Visão global ativada.');
        }

        $company = Company::query()->findOrFail($data['company_id']);
        $request->session()->put('master_company_id', $company->id);

        return redirect()->route('dashboard')->with('success', 'Acessando os dados de '.$company->name.'.');
    }

    public function setActive(Request $request, int $company): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $record = Company::query()->findOrFail($company);
        $active = (bool) $data['is_active'];
        $record->forceFill(['is_active' => $active])->save();

        if (! $active && (int) $request->session()->get('master_company_id') === $record->id) {
            $request->session()->forget('master_company_id');
        }

        return back()->with('success', $active ? 'Empresa ativada.' : 'Empresa desativada. Os membros perderão o acesso enquanto ela estiver inativa.');
    }
}
