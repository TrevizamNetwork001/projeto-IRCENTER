<?php

namespace App\Http\Requests;

use App\Models\ExternalIntegration;
use App\Support\ExternalEndpointGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

abstract class ExternalIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function integrationRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(ExternalIntegration::types()),
            ],
            'endpoint' => [
                'required',
                'url:https',
                'max:2048',
            ],
            'authentication_type' => [
                'required',
                'string',
                Rule::in(
                    ExternalIntegration::authenticationTypes()
                ),
            ],
            'username' => [
                'nullable',
                'string',
                'max:255',
            ],
            'secret' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'timeout_seconds' => [
                'required',
                'integer',
                'min:2',
                'max:30',
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $auth = $this->input('authentication_type');
            $existing = $this->route('externalIntegration');

            $hasStoredSecret = $existing instanceof ExternalIntegration
                && $existing->secret !== null;

            if (
                $auth === ExternalIntegration::AUTH_BASIC
                && ! $this->filled('username')
            ) {
                $validator->errors()->add(
                    'username',
                    'A autenticação básica exige um usuário.'
                );
            }

            if (
                in_array(
                    $auth,
                    [
                        ExternalIntegration::AUTH_BASIC,
                        ExternalIntegration::AUTH_BEARER,
                    ],
                    true
                )
                && ! $this->filled('secret')
                && ! $hasStoredSecret
            ) {
                $validator->errors()->add(
                    'secret',
                    'O tipo de autenticação selecionado exige um segredo.'
                );
            }

            try {
                app(ExternalEndpointGuard::class)->validate(
                    (string) $this->input('endpoint')
                );
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add(
                    'endpoint',
                    $exception->getMessage()
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $auth = strtolower(
            trim((string) $this->input(
                'authentication_type',
                ExternalIntegration::AUTH_NONE
            ))
        );

        $this->merge([
            'name' => $this->cleanString('name'),
            'type' => strtolower(
                trim((string) $this->input('type'))
            ),
            'endpoint' => rtrim(
                trim((string) $this->input('endpoint')),
                '/'
            ),
            'authentication_type' => $auth,
            'username' => $auth === ExternalIntegration::AUTH_BASIC
                ? $this->cleanString('username')
                : null,
            'secret' => $auth === ExternalIntegration::AUTH_NONE
                ? null
                : $this->cleanString('secret'),
            'timeout_seconds' => $this->integer(
                'timeout_seconds'
            ),
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
}
