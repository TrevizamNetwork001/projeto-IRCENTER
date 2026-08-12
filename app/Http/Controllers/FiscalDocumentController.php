<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorizeFiscalDocumentRequest;
use App\Http\Requests\StoreFiscalDocumentRequest;
use App\Http\Requests\UploadFiscalArtifactRequest;
use App\Models\Client;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\BillingItem;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Fiscal\Actions\AuthorizeFiscalDocumentManually;
use App\Modules\Fiscal\Actions\CancelFiscalDraft;
use App\Modules\Fiscal\Actions\CreateFiscalDocument;
use App\Modules\Fiscal\Actions\PrepareFiscalDocument;
use App\Modules\Fiscal\Actions\ReturnFiscalDocumentToDraft;
use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Models\FiscalArtifact;
use App\Modules\Fiscal\Models\FiscalCustomerProfile;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Models\FiscalServiceProfile;
use App\Modules\Fiscal\Services\FiscalArtifactStore;
use App\Modules\Fiscal\Services\FiscalDocumentReadinessService;
use App\Modules\Shared\Models\DomainAuditEvent;
use DOMDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FiscalDocumentController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorizeOperator();
        $clientId = $request->integer('client_id') ?: null;
        return view('fiscal.documents.create', ['clients' => Client::query()->where('active', true)->orderBy('legal_name')->get(), 'issuers' => FiscalIssuerProfile::query()->where('active', true)->orderBy('legal_name')->get(), 'services' => FiscalServiceProfile::query()->where('active', true)->orderBy('fiscal_description')->get(), 'selectedClientId' => $clientId]);
    }

    public function store(StoreFiscalDocumentRequest $request, CreateFiscalDocument $create): RedirectResponse
    {
        $data = $request->validated();
        $client = Client::query()->findOrFail($data['client_id']);
        $customer = FiscalCustomerProfile::query()->where('core_client_id', $client->id)->firstOrFail();
        $issuer = FiscalIssuerProfile::query()->where('active', true)->findOrFail($data['issuer_id']);
        $service = FiscalServiceProfile::query()->where('active', true)->findOrFail($data['service_id']);
        $this->validateFinancialOwnership($client->id, $data);
        $document = $create->execute(['core_client_id' => $client->id, 'fiscal_issuer_profile_id' => $issuer->id, 'fiscal_customer_profile_id' => $customer->id, 'billing_contract_id' => $data['billing_contract_id'] ?? null, 'invoice_id' => $data['invoice_id'] ?? null, 'charge_id' => $data['charge_id'] ?? null, 'billing_item_id' => $data['billing_item_id'] ?? $service->billing_item_id, 'competence_date' => $data['competence_date'], 'service_date' => $data['service_date'] ?? null, 'discount_amount' => $data['discount_amount'] ?? '0', 'deduction_amount' => $data['deduction_amount'] ?? '0', 'summary' => $data['summary'] ?? null, 'idempotency_key' => 'manual-create-'.Str::uuid(), 'emission_origin' => 'manual'], [['fiscal_service_profile_id' => $service->id, 'billing_item_id' => $data['billing_item_id'] ?? $service->billing_item_id, 'description' => $data['description'], 'quantity' => $data['quantity'], 'unit_amount' => $data['unit_amount']]], $request->user()->id);
        return redirect()->route('fiscal.documents.show', $document)->with('success', 'Rascunho fiscal criado.');
    }

    public function show(FiscalDocument $fiscalDocument, FiscalDocumentReadinessService $readiness): View
    {
        $fiscalDocument->load(['issuer', 'customer', 'items.service', 'artifacts']);
        return view('fiscal.documents.show', ['document' => $fiscalDocument, 'readiness' => $readiness->evaluate($fiscalDocument), 'timeline' => DomainAuditEvent::query()->where('module', 'fiscal')->where('entity_type', FiscalDocument::class)->where('entity_id', (string) $fiscalDocument->id)->latest('created_at')->get(), 'portalUrl' => config('finance_fiscal.fiscal.portal_url')]);
    }

    public function ready(FiscalDocument $fiscalDocument, PrepareFiscalDocument $prepare): RedirectResponse
    {
        $this->authorizeOperator();
        try { $prepare->execute($fiscalDocument, auth()->id()); } catch (\DomainException|\LogicException $exception) { return back()->withErrors(['readiness' => $exception->getMessage()]); }
        return back()->with('success', 'Documento pronto para emissão manual.');
    }

    public function draft(FiscalDocument $fiscalDocument, ReturnFiscalDocumentToDraft $action): RedirectResponse
    {
        $this->authorizeOperator();
        $action->execute($fiscalDocument, auth()->id());
        return back()->with('success', 'Documento devolvido para revisão.');
    }

    public function authorizeManual(AuthorizeFiscalDocumentRequest $request, FiscalDocument $fiscalDocument, AuthorizeFiscalDocumentManually $action): RedirectResponse
    {
        $action->execute($fiscalDocument, $request->validated(), $request->user()->id);
        return back()->with('success', 'NFS-e registrada como autorizada manualmente.');
    }

    public function cancel(FiscalDocument $fiscalDocument, CancelFiscalDraft $action): RedirectResponse
    {
        $this->authorizeOperator();
        $action->execute($fiscalDocument, auth()->id());
        return back()->with('success', 'Rascunho cancelado internamente. Nenhuma operação foi enviada ao governo.');
    }

    public function upload(UploadFiscalArtifactRequest $request, FiscalDocument $fiscalDocument, FiscalArtifactStore $store): RedirectResponse
    {
        abort_unless($fiscalDocument->status === FiscalDocumentStatus::Authorized, 409);
        $file = $request->file('artifact');
        $contents = $file->getContent();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $type = $request->validated('type');
        if ($type === 'manual_nfse_xml') {
            abort_unless(in_array($mime, ['application/xml', 'text/xml', 'text/plain'], true), 422);
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $valid = $dom->loadXML($contents, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            abort_unless($valid, 422);
            $mime = 'application/xml';
        } else {
            abort_unless($mime === 'application/pdf' && str_starts_with($contents, '%PDF-'), 422);
        }
        $store->store($fiscalDocument, $type, $contents, $mime, $file->getClientOriginalName(), $request->user()->id);
        return back()->with('success', 'Artefato fiscal armazenado privadamente.');
    }

    public function download(FiscalDocument $fiscalDocument, FiscalArtifact $artifact): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        abort_unless($artifact->fiscal_document_id === $fiscalDocument->id, 404);
        return \Storage::disk($artifact->storage_disk)->download($artifact->storage_path, $artifact->original_filename ?: $artifact->type, ['Content-Type' => $artifact->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroyArtifact(FiscalDocument $fiscalDocument, FiscalArtifact $artifact, FiscalArtifactStore $store): RedirectResponse
    {
        $this->authorizeOperator();
        abort_unless($artifact->fiscal_document_id === $fiscalDocument->id, 404);
        $store->remove($artifact, auth()->id());
        return back()->with('success', 'Artefato removido.');
    }

    private function authorizeOperator(): void { abort_unless(auth()->user()?->isAdministrator(), 403); }

    private function validateFinancialOwnership(int $clientId, array $data): void
    {
        if (isset($data['billing_contract_id'])) abort_unless(BillingContract::query()->whereKey($data['billing_contract_id'])->where('core_client_id', $clientId)->exists(), 422);
        if (isset($data['invoice_id'])) abort_unless(Invoice::query()->whereKey($data['invoice_id'])->where('core_client_id', $clientId)->exists(), 422);
        if (isset($data['charge_id'])) abort_unless(Charge::query()->whereKey($data['charge_id'])->whereHas('invoice', fn ($query) => $query->where('core_client_id', $clientId))->exists(), 422);
        if (isset($data['billing_item_id'])) abort_unless(BillingItem::query()->whereKey($data['billing_item_id'])->exists(), 422);
    }
}
