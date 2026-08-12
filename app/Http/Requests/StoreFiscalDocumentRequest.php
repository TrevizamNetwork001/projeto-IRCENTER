<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdministrator() === true; }
    public function rules(): array
    {
        return ['client_id' => ['required', 'integer', 'exists:clients,id'], 'issuer_id' => ['required', 'integer'], 'service_id' => ['required', 'integer'], 'billing_contract_id' => ['nullable', 'integer'], 'invoice_id' => ['nullable', 'integer'], 'charge_id' => ['nullable', 'integer'], 'billing_item_id' => ['nullable', 'integer'], 'competence_date' => ['required', 'date'], 'service_date' => ['nullable', 'date'], 'description' => ['required', 'string', 'max:2000'], 'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,4})?$/'], 'unit_amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'discount_amount' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'deduction_amount' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'summary' => ['nullable', 'string', 'max:5000']];
    }
}
