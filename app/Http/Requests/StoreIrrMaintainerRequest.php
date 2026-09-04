<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIrrMaintainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mntner' => strtoupper(trim((string) $this->input('mntner'))),
            'admin_c' => strtoupper(trim((string) $this->input('admin_c'))),
            'tech_c' => strtoupper(trim((string) $this->input('tech_c'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'asn' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'mntner' => ['required', 'string', 'max:100', 'unique:irr_maintainers,mntner'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'admin_c' => ['required', 'string', 'max:100'],
            'tech_c' => ['required', 'string', 'max:100'],
            'descr' => ['nullable', 'string', 'max:255'],
            'notify_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
