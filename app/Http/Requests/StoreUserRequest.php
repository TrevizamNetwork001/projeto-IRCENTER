<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'role' => [
                'required',
                Rule::in(User::roles()),
            ],
            'avatar_key' => [
                'nullable',
                'string',
                Rule::in(array_keys(User::avatars())),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)
                    ->letters()
                    ->numbers(),
            ],
            'active' => ['required', 'boolean'],
            'must_change_password' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $avatarKey = trim((string) $this->input('avatar_key'));

        $this->merge([
            'avatar_key' => $avatarKey === '' ? null : $avatarKey,
            'email' => strtolower(
                trim((string) $this->input('email'))
            ),
            'active' => $this->boolean('active'),
            'must_change_password' => $this->boolean(
                'must_change_password'
            ),
        ]);
    }
}
