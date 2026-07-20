<?php

namespace App\Http\Requests;

use App\Support\PrefixNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrefixRequest extends FormRequest
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
            'autonomous_system_id' => [
                'nullable',
                'integer',
                Rule::exists('autonomous_systems', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'client_id',
                            $this->integer('client_id')
                        )
                    ),
            ],
            'prefix' => [
                'required',
                'string',
                'max:64',
                Rule::unique('prefixes', 'prefix'),
                function (string $attribute, mixed $value, $fail): void {
                    if (PrefixNormalizer::normalize($value) === null) {
                        $fail('O prefixo deve ser um CIDR IPv4 ou IPv6 válido.');
                    }
                },
            ],
            'ip_version' => ['required', 'integer', Rule::in([4, 6])],
            'description' => ['nullable', 'string', 'max:255'],
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
            'allocation_status' => [
                'required',
                'string',
                Rule::in([
                    'allocated',
                    'assigned',
                    'reserved',
                    'legacy',
                    'available',
                ]),
            ],
            'purpose' => ['nullable', 'string', 'max:100'],
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
            'autonomous_system_id' => 'ASN',
            'prefix' => 'prefixo',
            'ip_version' => 'versão IP',
            'description' => 'descrição',
            'rir' => 'RIR',
            'country' => 'país',
            'allocation_status' => 'status de alocação',
            'purpose' => 'finalidade',
            'notes' => 'observações',
            'active' => 'status',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizedPrefix = PrefixNormalizer::normalize(
            $this->input('prefix')
        );

        $this->merge([
            'prefix' => $normalizedPrefix['prefix']
                ?? $this->cleanString('prefix'),
            'ip_version' => $normalizedPrefix['version']
                ?? $this->input('ip_version'),
            'description' => $this->cleanString('description'),
            'rir' => $this->normalizeRir(),
            'country' => strtoupper(
                $this->cleanString('country') ?: 'BR'
            ),
            'allocation_status' => strtolower(
                $this->cleanString('allocation_status') ?: 'allocated'
            ),
            'purpose' => $this->cleanString('purpose'),
            'notes' => $this->cleanString('notes'),
            'active' => $this->boolean('active'),
        ]);
    }

    private function normalizeRir(): ?string
    {
        $value = $this->cleanString('rir');

        return $value === null ? null : strtoupper($value);
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
