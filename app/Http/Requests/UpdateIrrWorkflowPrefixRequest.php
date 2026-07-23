<?php

namespace App\Http\Requests;

use App\Models\IrrWorkflowPrefix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIrrWorkflowPrefixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        return [
            'route_set_mode' => [
                'required',
                'string',
                Rule::in([
                    IrrWorkflowPrefix::MODE_EXACT,
                    IrrWorkflowPrefix::MODE_MORE_SPECIFICS,
                ]),
            ],
            'maximum_length' => [
                'nullable',
                'integer',
                'min:0',
                'max:128',
            ],
            'generate_route_object' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $prefix = $this->route('workflowPrefix');

            if (! $prefix instanceof IrrWorkflowPrefix) {
                return;
            }

            if (
                $this->input('route_set_mode')
                !== IrrWorkflowPrefix::MODE_MORE_SPECIFICS
            ) {
                return;
            }

            $maximumLength = $this->integer('maximum_length');
            $baseLength = $prefix->prefixLength();
            $familyMaximum = $prefix->ip_version === 6 ? 128 : 32;

            if ($maximumLength <= $baseLength) {
                $validator->errors()->add(
                    'maximum_length',
                    'O limite deve ser maior que o tamanho original do prefixo.'
                );
            }

            if ($maximumLength > $familyMaximum) {
                $validator->errors()->add(
                    'maximum_length',
                    "O limite máximo para IPv{$prefix->ip_version} é /{$familyMaximum}."
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'generate_route_object' => $this->boolean(
                'generate_route_object'
            ),
        ]);
    }
}
