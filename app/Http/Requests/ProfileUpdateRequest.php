<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                $this->user()->isPlatformMasterIdentity()
                    ? Rule::in([User::PLATFORM_MASTER_EMAIL])
                    : Rule::unique(User::class)->ignore($this->user()->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $secondary = $this->user()->role === 'admin' ? $this->user()->company?->secondary_recovery_email : null;
                    if ($secondary && mb_strtolower($secondary) === mb_strtolower((string) $value)) {
                        $fail('O e-mail do administrador deve ser diferente do e-mail de recuperação secundário da empresa.');
                    }
                },
            ],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'extensions:jpeg,jpg,png', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.image' => 'O arquivo enviado precisa ser uma imagem válida.',
            'photo.mimes' => 'A foto deve estar no formato JPEG, JPG ou PNG.',
            'photo.extensions' => 'A extensão da foto deve ser .jpeg, .jpg ou .png.',
            'photo.max' => 'A foto deve ter no máximo 2 MB.',
        ];
    }
}
