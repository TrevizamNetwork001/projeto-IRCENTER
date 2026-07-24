<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoutingIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperate() === true;
    }

    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:10000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $message = trim((string) $this->input('message'));

        $this->merge([
            'message' => $message === '' ? null : $message,
        ]);
    }
}
