<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutonomousSystemRequest extends FormRequest
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
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id'),
            ],
            'asn' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
                Rule::unique('autonomous_systems', 'asn'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rir' => [
                'nullable',
                'string',
                Rule::in([
                    'AFRINIC',
                    'APNIC',
                    'ARIN',
                    'LACNIC',
                    'RIPE NCC',
                    'OTHER',
                ]),
            ],
            'country' => ['required', 'string', 'size:2'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'noc_contact' => ['nullable', 'string', 'max:255'],
            'noc_email' => ['nullable', 'email', 'max:255'],
            'noc_phone' => ['nullable', 'string', 'max:30'],
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
            'client_id' => 'cliente',
            'asn' => 'ASN',
            'name' => 'nome',
            'description' => 'descrição',
            'rir' => 'RIR',
            'country' => 'país',
            'website' => 'site',
            'noc_contact' => 'contato do NOC',
            'noc_email' => 'e-mail do NOC',
            'noc_phone' => 'telefone do NOC',
            'notes' => 'observações',
            'active' => 'status',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'asn' => $this->normalizeAsn(),
            'name' => $this->cleanString('name'),
            'description' => $this->cleanString('description'),
            'rir' => $this->normalizeRir(),
            'country' => strtoupper(
                $this->cleanString('country') ?: 'BR'
            ),
            'website' => $this->cleanString('website'),
            'noc_contact' => $this->cleanString('noc_contact'),
            'noc_email' => $this->normalizeEmail('noc_email'),
            'noc_phone' => $this->cleanString('noc_phone'),
            'notes' => $this->cleanString('notes'),
            'active' => $this->boolean('active'),
        ]);
    }

    private function normalizeAsn(): mixed
    {
        $value = $this->input('asn');

        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        $normalized = preg_replace('/^AS/i', '', trim((string) $value));

        return ctype_digit((string) $normalized)
            ? (int) $normalized
            : $value;
    }

    private function normalizeRir(): ?string
    {
        $value = $this->cleanString('rir');

        return $value === null ? null : strtoupper($value);
    }

    private function normalizeEmail(string $field): ?string
    {
        $value = $this->cleanString($field);

        return $value === null ? null : mb_strtolower($value);
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
}
