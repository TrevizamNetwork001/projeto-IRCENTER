<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'avatar_key' => [
                'nullable',
                'string',
                Rule::in(array_keys(User::avatars())),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'avatar_key' => 'avatar',
        ];
    }

    protected function prepareForValidation(): void
    {
        $value = trim((string) $this->input('avatar_key'));

        $this->merge([
            'avatar_key' => $value === '' ? null : $value,
        ]);
    }
}
