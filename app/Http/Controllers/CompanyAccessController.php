<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Support\BrazilianTaxDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAccessController extends Controller
{
    private const PERMISSIONS = ['can_manage_projects', 'can_view_reports'];

    public function users(Request $request): Response
    {
        $companyId = $this->selectedCompanyId($request);

        return Inertia::render('Company/Users', [
            'users' => User::query()->where('company_id', $companyId)->orderBy('name')->get([
                'id', 'name', 'email', 'cpf', 'role', 'permissions', 'is_active', 'last_login_at', 'created_at',
            ]),
            'permissionOptions' => self::PERMISSIONS,
            'companies' => $request->user()->hasRole('master') ? Company::query()->orderBy('name')->get(['id', 'name', 'document_type', 'document_number']) : [],
            'selectedCompanyId' => $companyId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'cpf' => $this->normalizeCpf($request->input('cpf')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::notIn([User::platformMasterEmail()]), 'unique:users,email'],
            'cpf' => ['nullable', 'string', 'size:11', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! BrazilianTaxDocument::isValid('CPF', (string) $value)) {
                    $fail('Informe um CPF válido.');
                }
            }, Rule::unique('users', 'cpf')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['boolean'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
        ]);

        $companyId = $request->user()->hasRole('master')
            ? (int) ($data['company_id'] ?? 0)
            : (int) $request->user()->company_id;
        abort_unless($companyId > 0, 422, 'Selecione a empresa de destino.');

        if ($data['role'] === 'admin') {
            $this->assertNoOtherAdministrator($companyId);
            $this->assertDistinctFromRecoveryEmail($companyId, $data['email']);
        }

        $user = User::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'cpf' => $data['cpf'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'permissions' => $this->permissions($data['permissions'] ?? []),
            'is_active' => true,
        ]);

        return back()->with('success', 'Usuário criado.')->with('createdUserId', $user->id);
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $target = $this->target($request, $user);
        $request->merge([
            'cpf' => $this->normalizeCpf($request->input('cpf')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::notIn([User::platformMasterEmail()]), Rule::unique('users', 'email')->ignore($target->id)],
            'cpf' => ['nullable', 'string', 'size:11', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! BrazilianTaxDocument::isValid('CPF', (string) $value)) {
                    $fail('Informe um CPF válido.');
                }
            }, Rule::unique('users', 'cpf')->ignore($target->id)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        if ($target->is($request->user()) && $data['role'] !== $target->role) {
            throw ValidationException::withMessages(['role' => 'Não é permitido alterar o próprio perfil administrativo.']);
        }

        $this->protectLastAdmin($target, $data['role'], $target->is_active);
        if ($data['role'] === 'admin') {
            if ($target->role !== 'admin') {
                $this->assertNoOtherAdministrator((int) $target->company_id);
            }
            $this->assertDistinctFromRecoveryEmail((int) $target->company_id, $data['email']);
        }

        $target->fill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'cpf' => $data['cpf'] ?? null,
            'role' => $data['role'],
            'permissions' => $this->permissions($data['permissions'] ?? []),
        ])->save();

        return back()->with('success', 'Perfil atualizado.');
    }

    /**
     * Hand the company administration to another active member. The target becomes the
     * only administrator; the previous administrator(s) become regular users.
     */
    public function transferAdmin(Request $request, int $user): RedirectResponse
    {
        $target = $this->target($request, $user);
        $companyId = (int) $target->company_id;

        if (! $request->user()->hasRole('master') && (int) $request->user()->company_id !== $companyId) {
            abort(404);
        }
        if ($target->role === 'admin') {
            throw ValidationException::withMessages(['role' => 'Este usuário já é o administrador da empresa.']);
        }
        if (! $target->is_active) {
            throw ValidationException::withMessages(['role' => 'Ative o usuário antes de transferir a administração.']);
        }
        $this->assertDistinctFromRecoveryEmail($companyId, $target->email);

        DB::transaction(function () use ($target, $companyId): void {
            User::query()->where('company_id', $companyId)->where('role', 'admin')->whereKeyNot($target->id)->get()
                ->each(fn (User $previous) => $previous->forceFill([
                    'role' => 'user',
                    'permissions' => $this->permissions(array_fill_keys(self::PERMISSIONS, true)),
                ])->save());

            $target->forceFill(['role' => 'admin', 'permissions' => []])->save();
        });

        return back()->with('success', 'Administração da empresa transferida.');
    }

    public function setActive(Request $request, int $user): RedirectResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        $target = $this->target($request, $user);
        $active = (bool) $request->boolean('is_active');

        abort_unless(! $target->is($request->user()) || $active, 422, 'Não é possível desativar a própria conta.');
        $this->protectLastAdmin($target, $target->role, $active);

        $target->forceFill(['is_active' => $active])->save();

        // Login checks the active flag on every request; deleting persisted sessions also ends existing sessions promptly.
        if (! $active && config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $target->id)->delete();
        }

        return back()->with('success', $active ? 'Usuário ativado.' : 'Usuário desativado.');
    }

    public function audit(Request $request): Response
    {
        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'action' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::query();
        $selectedCompanyId = null;
        if (! $request->user()->hasRole('master')) {
            $selectedCompanyId = (int) $request->user()->company_id;
            $query->where('company_id', $selectedCompanyId);
        } elseif ($request->has('company_id')) {
            if ($request->filled('company_id')) {
                $selectedCompanyId = $request->integer('company_id');
                $query->where('company_id', $selectedCompanyId);
            }
        } elseif ($request->session()->has('master_company_id')) {
            $selectedCompanyId = (int) $request->session()->get('master_company_id');
            $query->where('company_id', $selectedCompanyId);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', mb_substr((string) $request->input('action'), 0, 80));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        return Inertia::render('Company/Audit', [
            'events' => $query->with('user:id,name,email')->latest('created_at')->paginate(50)->withQueryString(),
            'filters' => $request->only('user_id', 'action', 'from', 'to'),
            'users' => User::query()->when($selectedCompanyId, fn ($users) => $users->where('company_id', $selectedCompanyId))->orderBy('name')->get(['id', 'name']),
            'companies' => $request->user()->hasRole('master') ? Company::query()->orderBy('name')->get(['id', 'name']) : [],
            'selectedCompanyId' => $selectedCompanyId,
        ]);
    }

    private function target(Request $request, int $id): User
    {
        $target = User::query()
            ->when(! $request->user()->hasRole('master'), fn ($users) => $users->where('company_id', $request->user()->company_id))
            ->findOrFail($id);
        abort_if($target->role === 'master', 404);

        return $target;
    }

    private function selectedCompanyId(Request $request): int
    {
        if (! $request->user()->hasRole('master')) {
            return (int) $request->user()->company_id;
        }

        $companyId = $request->integer('company_id') ?: (int) $request->session()->get('master_company_id', $request->user()->company_id);
        abort_unless(Company::query()->whereKey($companyId)->exists(), 404);

        return $companyId;
    }

    private function assertNoOtherAdministrator(int $companyId): void
    {
        if (User::query()->where('company_id', $companyId)->where('role', 'admin')->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Cada empresa possui apenas um administrador. Use "Transferir administração" para trocar o responsável.',
            ]);
        }
    }

    private function assertDistinctFromRecoveryEmail(int $companyId, string $email): void
    {
        $secondary = Company::query()->whereKey($companyId)->value('secondary_recovery_email');

        if ($secondary && mb_strtolower($secondary) === mb_strtolower($email)) {
            throw ValidationException::withMessages([
                'email' => 'O e-mail do administrador deve ser diferente do e-mail de recuperação secundário da empresa.',
            ]);
        }
    }

    private function protectLastAdmin(User $target, string $newRole, bool $newActive): void
    {
        if ($target->role !== 'admin' || ! $target->is_active || ($newRole === 'admin' && $newActive)) {
            return;
        }

        $remaining = User::query()
            ->where('company_id', $target->company_id)
            ->where('role', 'admin')->where('is_active', true)
            ->whereKeyNot($target->id)->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages(['role' => 'A empresa precisa manter pelo menos um administrador ativo.']);
        }
    }

    private function permissions(array $permissions): array
    {
        $normalized = [];
        foreach (self::PERMISSIONS as $permission) {
            $normalized[$permission] = (bool) ($permissions[$permission] ?? false);
        }

        return $normalized;
    }

    private function normalizeCpf(mixed $cpf): ?string
    {
        if (! is_string($cpf) || trim($cpf) === '') {
            return null;
        }

        return preg_replace('/\D+/', '', $cpf) ?? '';
    }
}
