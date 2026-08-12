<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UploadFiscalArtifactRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdministrator() === true; }
    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['manual_nfse_xml', 'manual_danfse_pdf'])], 'artifact' => ['required', 'file', 'max:'.(int) config('finance_fiscal.fiscal.artifact_max_kb', 5120)]];
    }
}
