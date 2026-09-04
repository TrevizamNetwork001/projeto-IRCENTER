<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class IrrAsSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtoupper(trim((string) $this->input('name'))),
            'admin_c' => strtoupper(trim((string) $this->input('admin_c'))),
            'tech_c' => strtoupper(trim((string) $this->input('tech_c'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function asSetRules(?int $ignoreId = null): array
    {
        $uniqueRule = Rule::unique('irr_as_sets', 'name');

        if ($ignoreId !== null) {
            $uniqueRule->ignore($ignoreId);
        }

        return [
            'irr_maintainer_id' => ['required', 'integer', Rule::exists('irr_maintainers', 'id')],
            // aceita tanto "AS-CLIENTES" quanto a forma hierárquica
            // "AS64500:AS-CLIENTES" — em ambos, o último segmento tem que
            // começar com "AS-".
            'name' => ['required', 'string', 'max:100', 'regex:/(^|:)AS-[A-Z0-9-]+$/i', $uniqueRule],
            'descr' => ['nullable', 'string', 'max:255'],
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['required', 'string', 'max:100'],
            'admin_c' => ['required', 'string', 'max:100'],
            'tech_c' => ['required', 'string', 'max:100'],
            'notify' => ['nullable', 'array'],
            'notify.*' => ['required', 'email', 'max:255'],
        ];
    }
}
