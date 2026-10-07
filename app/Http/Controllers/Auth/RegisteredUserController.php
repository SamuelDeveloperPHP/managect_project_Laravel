<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PrivacyController;
use App\Models\Company;
use App\Models\User;
use App\Support\BrazilianTaxDocument;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $documentType = strtoupper((string) $request->input('document_type', 'CNPJ'));
        $request->merge([
            'document_type' => $documentType,
            'company_document' => preg_replace('/\D+/', '', (string) ($request->input('company_document') ?? $request->input('company_cnpj'))),
            'cpf' => preg_replace('/\D+/', '', (string) $request->input('cpf')),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'company_name' => 'required|string|max:120',
            'document_type' => ['required', Rule::in(['CNPJ', 'CPF'])],
            'company_document' => ['required', 'digits:'.($documentType === 'CPF' ? 11 : 14), function (string $attribute, mixed $value, \Closure $fail) use ($documentType): void {
                if (! BrazilianTaxDocument::isValid($documentType, (string) $value)) {
                    $fail('Informe um '.$documentType.' válido.');
                }
            }, Rule::unique('companies', 'document_number')->where('document_type', $documentType)],
            'cpf' => ['required', 'digits:11', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! BrazilianTaxDocument::isValid('CPF', (string) $value)) {
                    $fail('Informe um CPF válido.');
                }
            }, Rule::unique('users', 'cpf')],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190', 'unique:'.User::class, Rule::notIn([User::platformMasterEmail()])],
            'secondary_recovery_email' => ['nullable', 'string', 'lowercase', 'email', 'max:190', 'different:email'],
            'password' => ['required', 'confirmed', Rules\Password::min(12)],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Para fazer o cadastro, confirme que leu e aceita a Política de Privacidade e os Termos de Uso.',
            'secondary_recovery_email.different' => 'O e-mail de recuperação secundário deve ser diferente do e-mail do administrador.',
        ]);

        $user = DB::transaction(function () use ($request, $data): User {
            $baseSlug = Str::slug($request->string('company_name')) ?: 'empresa';
            $slug = $baseSlug;
            $suffix = 2;

            while (Company::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $documentNumber = $data['company_document'];
            $company = Company::create([
                'name' => $request->string('company_name'),
                'slug' => $slug,
                'document_type' => $data['document_type'],
                'document_number' => $documentNumber,
                'cnpj' => $data['document_type'] === 'CNPJ' ? $documentNumber : null,
                'secondary_recovery_email' => $data['secondary_recovery_email'] ?? null,
                'is_active' => true,
            ]);

            return User::create([
                'company_id' => $company->id,
                'name' => $request->name,
                'email' => $request->email,
                'cpf' => $data['cpf'],
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'permissions' => [],
                'is_active' => true,
            ]);
        });

        PrivacyController::recordAcceptance($user, $request);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
