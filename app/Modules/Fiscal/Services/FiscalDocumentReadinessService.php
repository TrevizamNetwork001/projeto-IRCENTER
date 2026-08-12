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
        $blocked = function (string $code, string $message) use (&$issues): void {
            $issues[] = ['level' => 'BLOCKED', 'code' => $code, 'message' => $message];
        };
        $warning = function (string $code, string $message) use (&$issues): void {
            $issues[] = ['level' => 'WARNING', 'code' => $code, 'message' => $message];
        };

        if (! $document->issuer?->active || ! $document->issuer?->document || ! $document->issuer?->legal_name || ! $document->issuer?->municipality_code) {
            $blocked('issuer.incomplete', 'O perfil fiscal do emitente está incompleto ou inativo.');
        }
        if (! $document->customer?->document) {
            $blocked('customer.document', 'O tomador não possui CPF/CNPJ fiscal.');
        }
        if (! $document->customer?->legal_name) {
            $blocked('customer.name', 'O tomador não possui nome ou razão social fiscal.');
        }
        if (! $document->customer?->municipality || ! $document->customer?->state || ! $document->customer?->municipality_code) {
            $blocked('customer.location', 'Município, UF e código IBGE do tomador são necessários.');
        }
        if ($document->items->isEmpty()) {
            $blocked('items.empty', 'Inclua ao menos um item fiscal.');
        }
        foreach ($document->items as $item) {
            if (! trim((string) $item->description)) {
                $blocked('service.description', 'A descrição fiscal do serviço é obrigatória.');
            }
            if (! $item->service?->active || ! $item->service?->national_service_code) {
                $blocked('service.classification', 'Selecione um serviço fiscal ativo e classificado.');
            }
        }
        if (bccomp((string) $document->services_amount, '0', 2) <= 0 || bccomp((string) $document->net_amount, '0', 2) <= 0) {
            $blocked('values.invalid', 'Os valores do serviço e líquido devem ser positivos.');
        }
        if (! $document->customer?->fiscal_email) {
            $warning('customer.email', 'O e-mail fiscal do tomador não foi informado.');
        }
        if (! $document->customer?->address_complement) {
            $warning('customer.address_complement', 'O complemento do endereço não foi informado.');
        }

        $hasBlocked = collect($issues)->contains(fn (array $issue) => $issue['level'] === 'BLOCKED');

        return new FiscalReadinessResult($hasBlocked ? 'BLOCKED' : ($issues === [] ? 'READY' : 'WARNING'), $issues);
    }
}
