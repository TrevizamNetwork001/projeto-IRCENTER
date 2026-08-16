<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Data\FiscalReadinessResult;
use App\Modules\Fiscal\Models\FiscalDocument;

final class FiscalDocumentReadinessService
{
    public function evaluate(FiscalDocument $document): FiscalReadinessResult
    {
        $document->loadMissing(['issuer', 'customer', 'items.service']);
        $issues = [];
        $blocked = function (string $code, string $message, string $area) use (&$issues): void {
            $issues[] = ['level' => 'BLOCKED', 'code' => $code, 'message' => $message, 'area' => $area];
        };
        $warning = function (string $code, string $message, string $area) use (&$issues): void {
            $issues[] = ['level' => 'WARNING', 'code' => $code, 'message' => $message, 'area' => $area];
        };

        if (! $document->issuer?->active || ! $document->issuer?->document || ! $document->issuer?->legal_name || ! $document->issuer?->municipality_code) {
            $blocked('issuer.incomplete', 'Complete o cadastro fiscal do emitente antes de preparar a emissão.', 'Emitente');
        }
        if (! $document->customer?->document) {
            $blocked('customer.document', 'Informe o CPF/CNPJ do tomador.', 'Cadastro fiscal do cliente');
        }
        if (! $document->customer?->legal_name) {
            $blocked('customer.name', 'Informe o nome ou a razão social do tomador.', 'Cadastro fiscal do cliente');
        }
        if (! $document->customer?->municipality || ! $document->customer?->state || ! $document->customer?->municipality_code) {
            $blocked('customer.location', 'Cadastre o município, a UF e o código IBGE do tomador.', 'Cadastro fiscal do cliente');
        }
        if ($document->items->isEmpty()) {
            $blocked('items.empty', 'Selecione um serviço fiscal.', 'Serviço');
        }
        foreach ($document->items as $item) {
            if (! trim((string) $item->description)) {
                $blocked('service.description', 'Informe a descrição do serviço.', 'Serviço');
            }
            if (! $item->service?->active || ! $item->service?->national_service_code) {
                $blocked('service.classification', 'Selecione um serviço fiscal ativo com código de serviço nacional.', 'Serviço');
            }
        }
        if (bccomp((string) $document->services_amount, '0', 2) <= 0 || bccomp((string) $document->net_amount, '0', 2) <= 0) {
            $blocked('values.invalid', 'Informe um valor de serviço maior que zero.', 'Valores');
        }
        if (! $document->customer?->fiscal_email) {
            $warning('customer.email', 'O e-mail fiscal do tomador não foi informado.', 'Cadastro fiscal do cliente');
        }
        if (! $document->customer?->address_complement) {
            $warning('customer.address_complement', 'O complemento do endereço não foi informado.', 'Cadastro fiscal do cliente');
        }

        $hasBlocked = collect($issues)->contains(fn (array $issue) => $issue['level'] === 'BLOCKED');

        return new FiscalReadinessResult($hasBlocked ? 'BLOCKED' : ($issues === [] ? 'READY' : 'WARNING'), $issues);
    }
}
