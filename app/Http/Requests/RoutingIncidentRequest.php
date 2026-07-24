<?php

namespace App\Http\Requests;

use App\Models\AutonomousSystem;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class RoutingIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperate() === true;
    }

    protected function incidentRules(): array
    {
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
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('active', true)
                    ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'type' => [
                'required',
                'string',
                Rule::in(RoutingIncident::types()),
            ],
            'severity' => [
                'required',
                'string',
                Rule::in(RoutingIncident::severities()),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(RoutingIncident::statuses()),
            ],
            'source' => ['nullable', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:10000'],
            'impact' => ['nullable', 'string', 'max:10000'],
            'evidence' => ['nullable', 'string', 'max:20000'],
            'mitigation' => ['nullable', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'detected_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clientId = $this->integer('client_id') ?: null;

            $asn = $this->filled('autonomous_system_id')
                ? AutonomousSystem::query()->find(
                    $this->integer('autonomous_system_id')
                )
                : null;

            $prefix = $this->filled('prefix_id')
                ? Prefix::query()->find(
                    $this->integer('prefix_id')
                )
                : null;

            if (
                $asn !== null
                && $clientId !== null
                && $asn->client_id !== $clientId
            ) {
                $validator->errors()->add(
                    'autonomous_system_id',
                    'O ASN deve pertencer ao cliente selecionado.'
                );
            }

            if (
                $prefix !== null
                && $clientId !== null
                && $prefix->client_id !== $clientId
            ) {
                $validator->errors()->add(
                    'prefix_id',
                    'O prefixo deve pertencer ao cliente selecionado.'
                );
            }

            if (
                $asn !== null
                && $prefix !== null
                && $prefix->autonomous_system_id !== null
                && $prefix->autonomous_system_id !== $asn->id
            ) {
                $validator->errors()->add(
                    'prefix_id',
                    'O prefixo está vinculado a outro ASN.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => $this->nullableInteger('client_id'),
            'autonomous_system_id' => $this->nullableInteger(
                'autonomous_system_id'
            ),
            'prefix_id' => $this->nullableInteger('prefix_id'),
            'assigned_to_user_id' => $this->nullableInteger(
                'assigned_to_user_id'
            ),
            'title' => $this->cleanString('title'),
            'type' => strtolower(
                trim((string) $this->input('type'))
            ),
            'severity' => strtolower(
                trim((string) $this->input('severity'))
            ),
            'status' => strtolower(
                trim((string) $this->input(
                    'status',
                    RoutingIncident::STATUS_OPEN
                ))
            ),
            'source' => $this->cleanString('source'),
            'summary' => $this->cleanString('summary'),
            'impact' => $this->cleanString('impact'),
            'evidence' => $this->cleanString('evidence'),
            'mitigation' => $this->cleanString('mitigation'),
            'root_cause' => $this->cleanString('root_cause'),
            'external_reference' => $this->cleanString(
                'external_reference'
            ),
        ]);
    }

    private function nullableInteger(string $field): ?int
    {
        $value = $this->input($field);

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_INT
        ) !== false
            ? (int) $value
            : null;
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
