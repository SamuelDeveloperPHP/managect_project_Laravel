<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        abort_unless(app()->environment(['local', 'development', 'dev', 'testing']), 404);

        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'development', 'dev', 'testing']), 404);

        $request->validate([
            'name' => 'required|string|max:120',
            'company_name' => 'required|string|max:120',
            'document_type' => ['required', Rule::in(['CNPJ', 'CPF'])],
            'document_number' => ['required', 'string', 'max:18', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (! BrazilianTaxDocument::isValid((string) $request->input('document_type'), (string) $value)) {
                    $fail('Informe um CNPJ ou CPF válido.');
                }
            }, Rule::unique('companies', 'document_number')->where('document_type', $request->input('document_type'))],
            'email' => 'required|string|lowercase|email|max:190|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request): User {
            $baseSlug = Str::slug($request->string('company_name')) ?: 'empresa';
            $slug = $baseSlug;
            $suffix = 2;

            while (Company::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $documentType = $request->string('document_type')->toString();
            $documentNumber = preg_replace('/\D+/', '', (string) $request->input('document_number'));
            $company = Company::create([
                'name' => $request->string('company_name'),
                'slug' => $slug,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'is_active' => true,
            ]);

            return User::create([
                'company_id' => $company->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'permissions' => [],
                'is_active' => true,
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
