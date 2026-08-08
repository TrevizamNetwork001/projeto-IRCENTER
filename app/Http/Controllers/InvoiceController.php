<?php

namespace App\Http\Controllers;

use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
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
            ->withCount('items')
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
                                );
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
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.invoices.index', [
            'invoices' => $invoices,
            'search' => $search,
            'status' => $status,
            'competence' => $competence,
            'financeEnabled' =>
                $this->financeEnabled(),
        ]);
    }

    public function show(
        Invoice $invoice,
    ): View {
        $invoice->load('items');

        $charges = Charge::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->latest('id')
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
