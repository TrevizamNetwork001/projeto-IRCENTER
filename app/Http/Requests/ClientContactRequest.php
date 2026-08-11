<?php

namespace App\Http\Requests;

use App\Models\ClientContact;
use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        $contact = $this->route('contact');

        return [
            'type' => [
                'required',
                Rule::in(ClientContact::TYPES),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('client_contacts', 'email')
                    ->where(fn ($query) => $query
                        ->where('client_id', $client?->getKey())
                        ->where('type', $this->input('type')))
                    ->ignore($contact?->getKey()),
            ],
            'phone' => [
                'nullable',
                'string',
                new BrazilianPhone(),
            ],
            'active' => ['required', 'boolean'],
            'is_primary' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'name' => 'nome',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'active' => 'status',
            'is_primary' => 'principal',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->cleanString('type'),
            'name' => $this->cleanString('name'),
            'email' => $this->cleanEmail(),
            'phone' => $this->cleanPhone(),
            'active' => $this->boolean('active'),
            'is_primary' => $this->boolean('is_primary'),
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

    private function cleanEmail(): ?string
    {
        $value = $this->cleanString('email');

        return $value === null ? null : mb_strtolower($value);
    }

    private function cleanPhone(): ?string
    {
        $value = $this->cleanString('phone');

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        return '+55'.$digits;
    }
}
