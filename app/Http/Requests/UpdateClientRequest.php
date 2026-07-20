<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
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
        $client = $this->route('client');

        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('clients', 'document')->ignore($client),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
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
            'document' => 'documento',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'website' => 'site',
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
            'document' => $this->cleanDocument(),
            'email' => $this->cleanEmail(),
            'phone' => $this->cleanString('phone'),
            'website' => $this->cleanString('website'),
            'city' => $this->cleanString('city'),
            'state' => $this->cleanString('state'),
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

    private function cleanDocument(): ?string
    {
        $value = $this->cleanString('document');

        if ($value === null) {
            return null;
        }

        return preg_replace('/[^A-Za-z0-9]/', '', $value) ?: null;
    }

    private function cleanEmail(): ?string
    {
        $value = $this->cleanString('email');

        return $value === null ? null : mb_strtolower($value);
    }
}
