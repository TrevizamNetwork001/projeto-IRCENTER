<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use App\Rules\CpfCnpj;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateFiscalCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdministrator() === true; }
    public function rules(): array
    {
        return ['document' => ['nullable', 'string', new CpfCnpj()], 'legal_name' => ['nullable', 'string', 'max:255'], 'municipal_registration' => ['nullable', 'string', 'max:40'], 'state_registration' => ['nullable', 'string', 'max:40'], 'fiscal_email' => ['nullable', 'email:rfc', 'max:255'], 'phone' => ['nullable', 'string', new BrazilianPhone()], 'postal_code' => ['nullable', 'regex:/^\d{8}$/'], 'street' => ['nullable', 'string', 'max:255'], 'address_number' => ['nullable', 'string', 'max:30'], 'address_complement' => ['nullable', 'string', 'max:100'], 'district' => ['nullable', 'string', 'max:100'], 'municipality' => ['nullable', 'string', 'max:120'], 'municipality_code' => ['nullable', 'regex:/^\d{7}$/'], 'state' => ['nullable', 'string', 'size:2'], 'country_code' => ['required', 'string', 'size:2'], 'country_numeric_code' => ['nullable', 'regex:/^\d{1,4}$/']];
    }
    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (array_keys($this->rules()) as $field) {
            $value = $this->input($field);
            $data[$field] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }
        foreach (['document', 'postal_code', 'municipality_code', 'country_numeric_code'] as $field) {
            $data[$field] = isset($data[$field]) ? (preg_replace('/\D/', '', $data[$field]) ?: null) : null;
        }
        $data['fiscal_email'] = isset($data['fiscal_email']) ? mb_strtolower($data['fiscal_email']) : null;
        $data['state'] = isset($data['state']) ? mb_strtoupper($data['state']) : null;
        $data['country_code'] = mb_strtoupper($data['country_code'] ?? 'BR');
        $this->merge($data);
    }
}
