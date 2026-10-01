<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Services\PdfSafetyScanner;
use App\Support\BrazilianTaxDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $company = $this->selectedCompany($request);
        $company->load(['documents' => fn ($query) => $query->latest()->limit(30)]);
        $profile = $company->only([
            'id', 'name', 'document_type', 'document_number', 'cnpj', 'domain', 'zip_code', 'street', 'number',
            'complement', 'neighborhood', 'city', 'state', 'contact_name', 'contact_email', 'contact_whatsapp',
            'admin_recovery_email', 'secondary_recovery_email',
        ]);
        if (! $profile['document_number'] && $profile['cnpj']) {
            $profile['document_type'] = 'CNPJ';
            $profile['document_number'] = preg_replace('/\D+/', '', $profile['cnpj']);
        }

        return Inertia::render('Company/Settings', [
            'company' => $profile,
            'hasLogo' => filled($company->logo_path),
            'documents' => $company->documents->map(fn (CompanyDocument $document) => [
                'id' => $document->id,
                'name' => $document->original_name,
                'size_bytes' => $document->size_bytes,
                'created_at' => $document->created_at,
            ]),
            'companies' => $request->user()->hasRole('master') ? Company::query()->orderBy('name')->get(['id', 'name']) : [],
            'selectedCompanyId' => $company->id,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->selectedCompany($request);
        $request->merge([
            'document_number' => preg_replace('/\D+/', '', (string) $request->input('document_number')),
            'domain' => filled($request->input('domain')) ? mb_strtolower(trim((string) $request->input('domain'))) : null,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'document_type' => ['required', Rule::in(['CNPJ', 'CPF'])],
            'document_number' => ['required', 'string', 'max:18', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (! BrazilianTaxDocument::isValid((string) $request->input('document_type'), (string) $value)) {
                    $fail('Informe um CNPJ ou CPF válido.');
                }
            }, Rule::unique('companies', 'document_number')->where('document_type', $request->input('document_type'))->ignore($company->id)],
            'domain' => ['nullable', 'string', 'max:190', 'regex:/^(?=.{1,190}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', Rule::unique('companies', 'domain')->ignore($company->id)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'admin_recovery_email' => ['nullable', 'email', 'max:190', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                $this->validateRecoveryDomain($value, $request->input('domain'), $fail);
            }],
            'secondary_recovery_email' => ['nullable', 'email', 'max:190', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if (is_string($value) && mb_strtolower($value) === mb_strtolower((string) $request->input('admin_recovery_email'))) {
                    $fail('O e-mail secundário deve ser diferente do e-mail principal.');
                }
                $this->validateRecoveryDomain($value, $request->input('domain'), $fail);
            }],
            'zip_code' => ['nullable', 'string', 'max:12'],
            'street' => ['nullable', 'string', 'max:190'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
        ]);

        $documentNumber = preg_replace('/\D+/', '', $data['document_number']) ?? '';
        $company->fill([
            'name' => trim($data['name']),
            'document_type' => $data['document_type'],
            'document_number' => $documentNumber,
            'cnpj' => $data['document_type'] === 'CNPJ' ? $documentNumber : null,
            'domain' => filled($data['domain'] ?? null) ? mb_strtolower(trim($data['domain'])) : null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => isset($data['contact_email']) ? mb_strtolower($data['contact_email']) : null,
            'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
            'admin_recovery_email' => isset($data['admin_recovery_email']) ? mb_strtolower($data['admin_recovery_email']) : null,
            'secondary_recovery_email' => isset($data['secondary_recovery_email']) ? mb_strtolower($data['secondary_recovery_email']) : null,
            'zip_code' => $data['zip_code'] ?? null,
            'street' => $data['street'] ?? null,
            'number' => $data['number'] ?? null,
            'complement' => $data['complement'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => isset($data['state']) ? mb_strtoupper($data['state']) : null,
        ])->save();

        return back()->with('success', 'Dados da empresa atualizados.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $company = $this->selectedCompany($request);
        $data = $request->validate(['logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
        $path = $data['logo']->store('company-logos/'.$company->id, 'local');
        abort_if(! $path, 500, 'Não foi possível guardar o logotipo.');
        $company->forceFill(['logo_path' => $path])->save();

        return back()->with('success', 'Logotipo enviado em armazenamento privado.');
    }

    public function uploadDocument(Request $request, PdfSafetyScanner $scanner): RedirectResponse
    {
        $company = $this->selectedCompany($request);
        $data = $request->validate(['document' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240']]);
        $file = $data['document'];
        $handle = fopen($file->getRealPath(), 'rb');
        $signature = $handle ? fread($handle, 5) : '';
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw ValidationException::withMessages(['document' => 'O arquivo não possui assinatura PDF válida.']);
        }

        try {
            $scanner->assertSafe($file);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }

        $directory = 'company-documents/'.$company->id;
        $path = $file->storeAs($directory, Str::uuid().'.pdf', 'local');
        abort_if(! $path, 500, 'Não foi possível armazenar o documento.');
        $name = preg_replace('/[^\pL\pN ._()\-]/u', '_', basename($file->getClientOriginalName())) ?: 'documento.pdf';
        CompanyDocument::create([
            'company_id' => $company->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => mb_substr($name, 0, 190),
            'stored_path' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('success', 'PDF verificado e armazenado em área privada.');
    }

    public function downloadDocument(Request $request, int $document): mixed
    {
        $company = $this->selectedCompany($request);
        $record = CompanyDocument::query()->where('company_id', $company->id)->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($record->stored_path), 404);

        return Storage::disk('local')->download($record->stored_path, $record->original_name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function selectedCompany(Request $request): Company
    {
        if (! $request->user()->hasRole('master')) {
            return Company::query()->whereKey($request->user()->company_id)->firstOrFail();
        }

        $companyId = $request->integer('company_id') ?: (int) $request->session()->get('master_company_id', $request->user()->company_id);

        return Company::query()->findOrFail($companyId);
    }

    private function validateRecoveryDomain(mixed $email, mixed $domain, \Closure $fail): void
    {
        if (! is_string($email) || $email === '' || ! is_string($domain) || $domain === '') {
            return;
        }

        if (mb_strtolower((string) strrchr($email, '@')) !== '@'.mb_strtolower($domain)) {
            $fail('Os e-mails de recuperação devem pertencer ao domínio cadastrado da empresa.');
        }
    }
}
