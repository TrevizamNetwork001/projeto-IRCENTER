<?php

namespace App\Http\Controllers;

use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Actions\CreateOneOffInvoice;
use App\Models\Client;
use App\Modules\Finance\Models\BillingItem;
use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\PaymentProviderEvent;
use App\Modules\Shared\Models\DomainAuditEvent;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly PaymentProvider $paymentProvider,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim(
            (string) $request->query('search', '')
        );

        $status = (string) $request->query(
            'status',
            'all'
        );

        $competence = trim(
            (string) $request->query('competence', '')
        );
        $issuedFrom = trim((string) $request->query('issued_from', ''));
        $issuedTo = trim((string) $request->query('issued_to', ''));
        $dueFrom = trim((string) $request->query('due_from', ''));
        $dueTo = trim((string) $request->query('due_to', ''));
        $chargeFilter = (string) $request->query('charge', 'all');

        $allowedStatuses = [
            'all',
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_OPEN,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
            Invoice::STATUS_CANCELED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if (
            $competence !== ''
            && preg_match(
                '/^\d{4}-(0[1-9]|1[0-2])$/',
                $competence
            ) !== 1
        ) {
            $competence = '';
        }

        $invoices = Invoice::query()
            ->with(['charges' => fn ($query) => $query->latest('id')])->withCount('items')
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'client_legal_name_snapshot',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'client_trade_name_snapshot',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'client_code_snapshot',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'client_document_snapshot',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'public_id',
                                    'like',
                                    "%{$search}%"
                                )->orWhereHas('charges', fn ($charge) =>
                                    $charge->where('provider_charge_id', 'like', "%{$search}%"));
                        }
                    );
                }
            )
            ->when(
                $status !== 'all',
                fn ($query) =>
                    $query->where('status', $status)
            )
            ->when(
                $competence !== '',
                fn ($query) =>
                    $query->where(
                        'competence_month',
                        $competence.'-01'
                    )
            )
            ->when($issuedFrom !== '', fn ($query) => $query->whereDate('issued_on', '>=', $issuedFrom))
            ->when($issuedTo !== '', fn ($query) => $query->whereDate('issued_on', '<=', $issuedTo))
            ->when($dueFrom !== '', fn ($query) => $query->whereDate('due_on', '>=', $dueFrom))
            ->when($dueTo !== '', fn ($query) => $query->whereDate('due_on', '<=', $dueTo))
            ->when($chargeFilter === 'with', fn ($query) => $query->whereHas('charges'))
            ->when($chargeFilter === 'without', fn ($query) => $query->whereDoesntHave('charges'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.invoices.index', [
            'invoices' => $invoices,
            'search' => $search,
            'status' => $status,
            'competence' => $competence,
            'issuedFrom' => $issuedFrom, 'issuedTo' => $issuedTo,
            'dueFrom' => $dueFrom, 'dueTo' => $dueTo,
            'chargeFilter' => $chargeFilter,
            'financeEnabled' =>
                $this->financeEnabled(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeWrite();
        return view('finance.invoices.create', [
            'clients' => Client::query()->where('active', true)->orderBy('legal_name')->get(),
            'items' => BillingItem::query()->where('active', true)->orderBy('name')->get(),
            'selectedClientId' => (int) $request->query('client_id', 0),
        ]);
    }

    public function store(Request $request, CreateOneOffInvoice $action): RedirectResponse
    {
        $this->authorizeWrite();
        $request->merge([
            'quantity' => str_replace(',', '.', trim((string) $request->input('quantity'))),
            'unit_amount' => str_replace(',', '.', trim((string) $request->input('unit_amount'))),
        ]);
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'billing_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'regex:/^\d{1,10}(\.\d{1,4})?$/'],
            'unit_amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);
        try {
            $invoice = $action->handle((int) $data['client_id'], (int) $data['billing_item_id'], $data['quantity'], $data['unit_amount'], $data['due_on'], auth()->id());
        } catch (DomainException|InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['finance' => $exception->getMessage()]);
        }
        return redirect()->route('finance.invoices.show', $invoice)->with('success', 'Fatura avulsa criada. A emissão da cobrança é uma etapa separada.');
    }

    public function show(
        Invoice $invoice,
    ): View {
        $invoice->load(['items', 'payments']);

        $charges = Charge::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->with('payment')
            ->latest('id')
            ->get();

        $paymentIds = $invoice->payments->pluck('id');
        $chargeIds = $charges->pluck('id');
        $receiptIds = PaymentProviderEvent::query()
            ->whereIn('charge_id', $chargeIds)
            ->whereNotNull('webhook_receipt_id')
            ->pluck('webhook_receipt_id')
            ->unique();

        $timeline = DomainAuditEvent::query()
            ->where('module', 'finance')
            ->where(function ($query) use ($invoice, $chargeIds, $paymentIds, $receiptIds): void {
                $query->where(function ($query) use ($invoice): void {
                    $query->where('entity_type', 'invoice')
                        ->where('entity_id', (string) $invoice->id);
                });

                if ($chargeIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($chargeIds): void {
                        $query->where('entity_type', 'charge')
                            ->whereIn('entity_id', $chargeIds->map(fn ($id) => (string) $id));
                    });
                }

                if ($paymentIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($paymentIds): void {
                        $query->where('entity_type', 'payment')
                            ->whereIn('entity_id', $paymentIds->map(fn ($id) => (string) $id));
                    });
                }

                if ($receiptIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($receiptIds): void {
                        $query->where('entity_type', 'payment_webhook_receipt')
                            ->whereIn('entity_id', $receiptIds->map(fn ($id) => (string) $id));
                    });
                }
            })
            ->latest('id')
            ->limit(50)
            ->get();

        $contract = null;

        if ($invoice->billing_contract_id) {
            $contract = BillingContract::query()
                ->find(
                    $invoice->billing_contract_id
                );
        }

        return view('finance.invoices.show', [
            'invoice' => $invoice,
            'charges' => $charges,
            'contract' => $contract,
            'payments' => $invoice->payments,
            'timeline' => $timeline,
            'financeEnabled' =>
                $this->financeEnabled(),

            'paymentProviderKey' =>
                $this->paymentProvider->key(),

            'paymentProviderLive' =>
                $this->paymentProvider->isLive(),

            'paymentLiveEnabled' =>
                (bool) config(
                    'finance_fiscal.finance.'
                    .'payment_live_enabled',
                    false
                ),

            'efiEnvironment' =>
                (string) config(
                    'finance_fiscal.providers.efi.environment',
                    'homologation'
                ),

            'availablePaymentMethods' =>
                array_values(
                    array_intersect(
                        [
                            Charge::METHOD_BOLETO,
                            Charge::METHOD_PIX,
                            Charge::METHOD_BOLETO_PIX,
                        ],
                        $this->paymentProvider
                            ->capabilities()
                    )
                ),

            'reconciliationAvailable' =>
                $this->paymentProvider
                    instanceof CorrelatablePaymentProvider
                && $this->paymentProvider->key()
                    !== 'fake',
        ]);
    }

    public function generate(
        Request $request,
        BillingContract $billingContract,
        GenerateInvoiceForContract $action,
    ): RedirectResponse {
        $this->authorizeWrite();

        $data = $request->validate([
            'competence' => [
                'required',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            ],
        ]);

        try {
            /*
             * A Action já é idempotente pela
             * generation_key contrato + competência.
             */
            $invoice = $action->handle(
                $billingContract->id,
                $data['competence'],
                auth()->id(),
            );
        } catch (
            DomainException|InvalidArgumentException $exception
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }

        return redirect()
            ->route(
                'finance.invoices.show',
                $invoice
            )
            ->with(
                'success',
                'Fatura disponível para a competência '
                .$data['competence'].'.'
            );
    }

    private function authorizeWrite(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );

        abort_unless(
            $this->financeEnabled(),
            503,
            'Módulo financeiro está desabilitado.'
        );
    }

    private function financeEnabled(): bool
    {
        return (bool) config(
            'finance_fiscal.finance.enabled',
            false
        );
    }
}
