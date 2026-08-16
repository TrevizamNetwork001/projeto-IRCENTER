<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\FiscalIssuerProfile;

final class FiscalIssuerReadinessService
{
    public function evaluate(?FiscalIssuerProfile $profile): array
    {
        if (! $profile) {
            return ['status' => 'INCOMPLETE', 'issues' => ['Emitente fiscal não configurado.']];
        }

        $required = [
            'document' => 'Informe o CNPJ do emitente.',
            'legal_name' => 'Informe a razão social.',
            'municipality' => 'Informe o município do emitente.',
            'municipality_code' => 'Informe o código IBGE do município.',
            'state' => 'Informe a UF.',
            'postal_code' => 'Informe o CEP.',
            'street' => 'Informe o endereço.',
            'address_number' => 'Informe o número do endereço.',
            'district' => 'Informe o bairro.',
        ];
        $issues = [];
        foreach ($required as $field => $message) {
            if (blank($profile->{$field})) {
                $issues[] = $message;
            }
        }

        return ['status' => $issues === [] ? 'READY' : 'INCOMPLETE', 'issues' => $issues];
    }
}
