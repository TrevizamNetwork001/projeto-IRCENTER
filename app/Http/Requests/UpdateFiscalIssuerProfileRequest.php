<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use App\Rules\CpfCnpj;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFiscalIssuerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        $profileId = FiscalIssuerProfile::query()->orderByDesc('active')->orderBy('id')->value('id');

        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'string', new CpfCnpj(), Rule::unique('finance_fiscal.fiscal_issuer_profiles', 'document')->ignore($profileId)],
            'municipal_registration' => ['nullable', 'string', 'max:40'],
            'municipality_code' => ['required', 'regex:/^\d{7}$/'],
            'municipality' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'regex:/^\d{8}$/'],
            'street' => ['required', 'string', 'max:255'],
            'address_number' => ['required', 'string', 'max:30'],
            'address_complement' => ['nullable', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', new BrazilianPhone()],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (array_keys($this->rules()) as $field) {
            $value = $this->input($field);
            $data[$field] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }
        foreach (['document', 'postal_code', 'municipality_code'] as $field) {
            $data[$field] = isset($data[$field]) ? (preg_replace('/\D/', '', $data[$field]) ?: null) : null;
        }
        $data['state'] = isset($data['state']) ? mb_strtoupper($data['state']) : null;
        $data['email'] = isset($data['email']) ? mb_strtolower($data['email']) : null;
        $data['active'] = $this->boolean('active');
        $this->merge($data);
    }

    public function messages(): array
    {
        return [
            'legal_name.required' => 'Informe a razão social do emitente.',
            'document.required' => 'Informe o CNPJ do emitente.',
            'municipality.required' => 'Informe o município do emitente.',
            'municipality_code.required' => 'Informe o código IBGE do município.',
            'state.required' => 'Informe a UF do emitente.',
        ];
    }
}
