<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AuthorizeFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdministrator() === true; }
    public function rules(): array
    {
        return ['confirmation' => ['accepted'], 'nfse_number' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9.\/-]+$/'], 'access_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9.-]+$/'], 'verification_code' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9.-]+$/'], 'authorized_at' => ['required', 'date'], 'manual_authorization_notes' => ['nullable', 'string', 'max:2000']];
    }
}
