<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIrrWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id'),
            ],
            'autonomous_system_id' => [
                'required',
                'integer',
                Rule::exists('autonomous_systems', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'client_id',
                            $this->integer('client_id')
                        )
                    ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'profile_key' => [
                'required',
                'string',
                Rule::in(array_keys(config('irr.profiles', []))),
            ],
            'irr_source' => ['required', 'string', 'max:50'],
            'destination_email' => ['nullable', 'email', 'max:255'],
            'maintainer' => ['required', 'string', 'max:100'],
            'as_set' => ['required', 'string', 'max:100'],
            'route_set' => ['required', 'string', 'max:100'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_handle' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $profileKey = strtolower(
            trim((string) $this->input('profile_key', 'manual'))
        );

        $profile = config(
            'irr.profiles.'.$profileKey,
            config('irr.profiles.manual', [])
        );

        $this->merge([
            'profile_key' => $profileKey,
            'irr_source' => strtoupper(
                trim((string) (
                    $this->input('irr_source')
                    ?: ($profile['source'] ?? 'LOCAL')
                ))
            ),
            'maintainer' => strtoupper(
                trim((string) $this->input('maintainer'))
            ),
            'as_set' => strtoupper(
                trim((string) $this->input('as_set'))
            ),
            'route_set' => strtoupper(
                trim((string) $this->input('route_set'))
            ),
            'contact_handle' => strtoupper(
                trim((string) $this->input('contact_handle'))
            ),
        ]);
    }
}
