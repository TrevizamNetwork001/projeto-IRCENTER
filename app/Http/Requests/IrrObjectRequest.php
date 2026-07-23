<?php

namespace App\Http\Requests;

use App\Models\AutonomousSystem;
use App\Models\IrrObject;
use App\Models\Prefix;
use App\Support\PrefixNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class IrrObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function irrRules(?IrrObject $ignore = null): array
    {
        $identityRule = Rule::unique('irr_objects', 'object_key')
            ->where(fn ($query) => $query
                ->where('object_type', $this->input('object_type'))
                ->where('source', $this->input('source'))
            );

        if ($ignore !== null) {
            $identityRule->ignore($ignore);
        }

        return [
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id'),
            ],
            'autonomous_system_id' => [
                'nullable',
                'integer',
                Rule::exists('autonomous_systems', 'id'),
            ],
            'prefix_id' => [
                'nullable',
                'integer',
                Rule::exists('prefixes', 'id'),
            ],
            'object_type' => [
                'required',
                'string',
                Rule::in(IrrObject::TYPES),
            ],
            'object_key' => [
                'required',
                'string',
                'max:100',
                $identityRule,
            ],
            'source' => ['required', 'string', 'max:50'],
            'maintainer' => ['nullable', 'string', 'max:100'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'pending',
                    'deprecated',
                    'error',
                ]),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'raw_text' => ['nullable', 'string', 'max:100000'],
            'attributes' => ['nullable', 'array'],
            'last_synced_at' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('object_type');

            $prefix = $this->filled('prefix_id')
                ? Prefix::query()->find($this->integer('prefix_id'))
                : null;

            $autonomousSystem = $this->filled('autonomous_system_id')
                ? AutonomousSystem::query()->find(
                    $this->integer('autonomous_system_id')
                )
                : null;

            $clientId = $this->filled('client_id')
                ? $this->integer('client_id')
                : null;

            if (in_array($type, ['route', 'route6'], true)) {
                $this->validateRouteObject(
                    $validator,
                    $type,
                    $prefix,
                    $autonomousSystem,
                    $clientId
                );
            }

            if ($type === 'aut-num') {
                $this->validateAutNum(
                    $validator,
                    $autonomousSystem,
                    $clientId
                );
            }

            if ($type === 'as-set') {
                if ($clientId === null) {
                    $validator->errors()->add(
                        'client_id',
                        'O objeto AS-set deve estar vinculado a um cliente.'
                    );
                }

                if (
                    ! str_starts_with(
                        strtoupper((string) $this->input('object_key')),
                        'AS-'
                    )
                ) {
                    $validator->errors()->add(
                        'object_key',
                        'A chave de um AS-set deve começar com AS-.'
                    );
                }

                if (
                    $autonomousSystem !== null
                    && $clientId !== null
                    && $autonomousSystem->client_id !== $clientId
                ) {
                    $validator->errors()->add(
                        'autonomous_system_id',
                        'O ASN selecionado deve pertencer ao mesmo cliente.'
                    );
                }
            }

            if ($type === 'mntner') {
                if ($clientId === null) {
                    $validator->errors()->add(
                        'client_id',
                        'O objeto maintainer deve estar vinculado a um cliente.'
                    );
                }

                if ($autonomousSystem !== null) {
                    $validator->errors()->add(
                        'autonomous_system_id',
                        'Objetos maintainer não devem possuir ASN vinculado.'
                    );
                }

                if ($prefix !== null) {
                    $validator->errors()->add(
                        'prefix_id',
                        'Objetos maintainer não devem possuir prefixo vinculado.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $type = strtolower(trim((string) $this->input('object_type')));
        $key = trim((string) $this->input('object_key'));

        if (in_array($type, ['route', 'route6'], true)) {
            $normalized = PrefixNormalizer::normalize($key);

            if ($normalized !== null) {
                $key = $normalized['prefix'];
            }
        }

        if ($type === 'aut-num') {
            $asn = preg_replace('/^AS/i', '', $key);

            if (ctype_digit((string) $asn)) {
                $key = 'AS'.(int) $asn;
            }
        }

        if ($type === 'mntner') {
            $key = strtoupper($key);
        }

        $clientId = $this->nullableInteger('client_id');
        $autonomousSystemId = $this->nullableInteger(
            'autonomous_system_id'
        );
        $prefixId = $this->nullableInteger('prefix_id');

        if ($type === 'mntner') {
            $autonomousSystemId = null;
            $prefixId = null;
        }

        if (in_array($type, ['aut-num', 'as-set'], true)) {
            $prefixId = null;
        }

        $this->merge([
            'client_id' => $clientId,
            'autonomous_system_id' => $autonomousSystemId,
            'prefix_id' => $prefixId,
            'object_type' => $type,
            'object_key' => $key,
            'source' => strtoupper(
                trim((string) $this->input('source', 'LOCAL'))
            ),
            'maintainer' => $this->nullableUppercase('maintainer'),
            'status' => strtolower(
                trim((string) $this->input('status', 'active'))
            ),
            'description' => $this->nullableString('description'),
            'raw_text' => $this->nullableString('raw_text'),
            'active' => $this->boolean('active'),
        ]);
    }

    private function validateRouteObject(
        Validator $validator,
        string $type,
        ?Prefix $prefix,
        ?AutonomousSystem $autonomousSystem,
        ?int $clientId
    ): void {
        if ($prefix === null) {
            $validator->errors()->add(
                'prefix_id',
                'Objetos route e route6 exigem um prefixo.'
            );

            return;
        }

        if ($autonomousSystem === null) {
            $validator->errors()->add(
                'autonomous_system_id',
                'Objetos route e route6 exigem um ASN de origem.'
            );
        }

        $expectedVersion = $type === 'route' ? 4 : 6;

        if ($prefix->ip_version !== $expectedVersion) {
            $validator->errors()->add(
                'prefix_id',
                $type === 'route'
                    ? 'Objetos route aceitam somente prefixos IPv4.'
                    : 'Objetos route6 aceitam somente prefixos IPv6.'
            );
        }

        if ($this->input('object_key') !== $prefix->prefix) {
            $validator->errors()->add(
                'object_key',
                'A chave do objeto deve corresponder ao prefixo selecionado.'
            );
        }

        if (
            $autonomousSystem !== null
            && $autonomousSystem->client_id !== $prefix->client_id
        ) {
            $validator->errors()->add(
                'autonomous_system_id',
                'O ASN e o prefixo devem pertencer ao mesmo cliente.'
            );
        }

        if (
            $clientId !== null
            && $clientId !== $prefix->client_id
        ) {
            $validator->errors()->add(
                'client_id',
                'O cliente deve ser o proprietário do prefixo selecionado.'
            );
        }
    }

    private function validateAutNum(
        Validator $validator,
        ?AutonomousSystem $autonomousSystem,
        ?int $clientId
    ): void {
        if ($autonomousSystem === null) {
            $validator->errors()->add(
                'autonomous_system_id',
                'Objetos aut-num exigem um ASN.'
            );

            return;
        }

        if (
            $this->input('object_key')
            !== $autonomousSystem->formattedAsn()
        ) {
            $validator->errors()->add(
                'object_key',
                'A chave do objeto deve corresponder ao ASN selecionado.'
            );
        }

        if (
            $clientId !== null
            && $clientId !== $autonomousSystem->client_id
        ) {
            $validator->errors()->add(
                'client_id',
                'O cliente deve ser o proprietário do ASN selecionado.'
            );
        }

        if ($this->filled('prefix_id')) {
            $validator->errors()->add(
                'prefix_id',
                'Objetos aut-num não devem possuir prefixo vinculado.'
            );
        }
    }

    private function nullableInteger(string $field): ?int
    {
        $value = $this->input($field);

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE
        );
    }

    private function nullableString(string $field): ?string
    {
        $value = $this->input($field);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableUppercase(string $field): ?string
    {
        $value = $this->nullableString($field);

        return $value === null ? null : strtoupper($value);
    }
}
