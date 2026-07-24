<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use App\Rules\CpfCnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                new CpfCnpj(),
                Rule::unique('clients', 'document'),
            ],
            'contract_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._\\/-]+$/',
                Rule::unique('clients', 'contract_number'),
            ],
            'generate_contract_number' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', new BrazilianPhone()],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\\d{8}$/'],
            'street' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:30'],
            'address_complement' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'document' => 'CPF ou CNPJ',
            'contract_number' => 'número do contrato',
            'generate_contract_number' => 'geração automática do contrato',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'website' => 'site',
            'postal_code' => 'CEP',
            'street' => 'logradouro',
            'address_number' => 'número',
            'address_complement' => 'complemento',
            'district' => 'bairro',
            'city' => 'cidade',
            'state' => 'estado',
            'country' => 'país',
            'notes' => 'observações',
            'active' => 'status',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'legal_name' => $this->cleanString('legal_name'),
            'trade_name' => $this->cleanString('trade_name'),
            'document' => $this->digitsOnly('document'),
            'contract_number' => $this->cleanContractNumber(),
            'generate_contract_number' => $this->boolean(
                'generate_contract_number'
            ),
            'email' => $this->cleanEmail(),
            'phone' => $this->cleanPhone(),
            'website' => $this->cleanWebsite(),
            'postal_code' => $this->digitsOnly('postal_code'),
            'street' => $this->cleanString('street'),
            'address_number' => $this->cleanString('address_number'),
            'address_complement' => $this->cleanString(
                'address_complement'
            ),
            'district' => $this->cleanString('district'),
            'city' => $this->cleanString('city'),
            'state' => $this->cleanState(),
            'country' => strtoupper(
                $this->cleanString('country') ?: 'BR'
            ),
            'notes' => $this->cleanString('notes'),
            'active' => $this->boolean('active'),
        ]);
    }

    private function cleanString(string $field): ?string
    {
        $value = $this->input($field);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function digitsOnly(string $field): ?string
    {
        $value = $this->cleanString($field);

        if ($value === null) {
            return null;
        }

        return preg_replace('/\D/', '', $value) ?: null;
    }

    private function cleanContractNumber(): ?string
    {
        $value = $this->cleanString('contract_number');

        return $value === null ? null : mb_strtoupper($value);
    }

    private function cleanPhone(): ?string
    {
        $value = $this->digitsOnly('phone');

        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, '55') && strlen($value) >= 12) {
            $value = substr($value, 2);
        }

        return '+55'.$value;
    }

    private function cleanWebsite(): ?string
    {
        $value = $this->cleanString('website');

        if ($value === null) {
            return null;
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        return $value;
    }

    private function cleanState(): ?string
    {
        $value = $this->cleanString('state');

        return $value === null ? null : mb_strtoupper($value);
    }

    private function cleanEmail(): ?string
    {
        $value = $this->cleanString('email');

        return $value === null ? null : mb_strtolower($value);
    }
}
