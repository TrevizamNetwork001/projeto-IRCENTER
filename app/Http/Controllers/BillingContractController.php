<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\SuspendBillingContract;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\BillingItem;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class BillingContractController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim(
            (string) $request->query('search', '')
        );

        $status = (string) $request->query(
            'status',
            'all'
        );

        $allowedStatuses = [
            'all',
            BillingContract::STATUS_DRAFT,
            BillingContract::STATUS_ACTIVE,
            BillingContract::STATUS_SUSPENDED,
            BillingContract::STATUS_ENDED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $contracts = BillingContract::query()
            ->with(['items' => fn ($query) => $query->where('active', true)])
            ->with(['items' => fn ($query) => $query->where('active', true)])
            ->with(['items' => fn ($query) => $query->where('active', true)])
            ->withCount('items')
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'client_legal_name_snapshot',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'client_trade_name_snapshot',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'client_code_snapshot',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'client_document_snapshot',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'public_id',
                                    'ilike',
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
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.contracts.index', [
            'contracts' => $contracts,
            'search' => $search,
            'status' => $status,
            'financeEnabled' =>
                $this->financeEnabled(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeWrite();

        $clients = Client::query()
            ->where('active', true)
            ->orderBy('legal_name')
            ->get([
                'id',
                'client_code',
                'legal_name',
                'trade_name',
                'document',
            ]);

        return view('finance.contracts.create', [
            'clients' => $clients,
            'billingItems' => BillingItem::query()
                ->where('active', true)->orderBy('name')->get(),
            'selectedClientId' => (int) request()->query('client_id', 0),
        ]);
    }

    public function store(
        Request $request,
        CreateBillingContract $action,
    ): RedirectResponse {
        $this->authorizeWrite();

        /*
         * A interface é pt-BR, então aceitamos vírgula
         * ou ponto como separador decimal.
         *
         * O domínio continua recebendo strings
         * normalizadas com ponto.
         */
        $request->merge([
            'quantity' => str_replace(
                ',',
                '.',
                trim((string) $request->input('quantity'))
            ),

            'unit_amount' => str_replace(
                ',',
                '.',
                trim((string) $request->input('unit_amount'))
            ),
        ]);

        $data = $request->validate([
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],

            'generation_day' => [
                'required',
                'integer',
                'min:1',
                'max:31',
            ],

            'due_day' => [
                'required',
                'integer',
                'min:1',
                'max:31',
            ],

            'billing_email_override' => [
                'nullable',
                'email',
                'max:255',
            ],

            'starts_on' => [
                'nullable',
                'date',
            ],

            'ends_on' => [
                'nullable',
                'date',
                'after_or_equal:starts_on',
            ],

            'service_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'billing_item_id' => [
                'nullable', 'integer',
            ],

            'description' => [
                'nullable',
                'required_without:billing_item_id',
                'string',
                'max:255',
            ],

            'quantity' => [
                'required',
                'regex:/^\d{1,10}(\.\d{1,4})?$/',
            ],

            'unit_amount' => [
                'required',
                'regex:/^\d{1,12}(\.\d{1,2})?$/',
            ],
        ], [
            'quantity.regex' =>
                'Quantidade deve ser um número com até 4 casas decimais.',

            'unit_amount.regex' =>
                'Valor unitário deve ser um número com até 2 casas decimais.',
        ]);

        $catalogItem = null;
        if (! empty($data['billing_item_id'])) {
            $catalogItem = BillingItem::query()
                ->whereKey($data['billing_item_id'])
                ->where('active', true)->first();
            if (! $catalogItem) {
                return back()->withInput()->withErrors([
                    'billing_item_id' => 'Selecione um item de cobrança ativo.',
                ]);
            }
            $data['description'] = $catalogItem->name;
            $data['service_code'] = 'CAT-'.$catalogItem->id;
        }

        try {
            $contract = $action->handle(
                clientId:
                    (int) $data['client_id'],

                attributes: [
                    'generation_day' =>
                        (int) $data['generation_day'],

                    'due_day' =>
                        (int) $data['due_day'],

                    'billing_email_override' =>
                        $data[
                            'billing_email_override'
                        ] ?? null,

                    'starts_on' =>
                        $data['starts_on'] ?? null,

                    'ends_on' =>
                        $data['ends_on'] ?? null,
                ],

                items: [
                    [
                        'billing_item_id' =>
                            $catalogItem?->id,
                        'service_code' =>
                            $data['service_code'] ?? null,

                        'description' =>
                            $data['description'],

                        'quantity' =>
                            $data['quantity'],

                        'unit_amount' =>
                            $data['unit_amount'],
                    ],
                ],

                actorUserId:
                    auth()->id(),
            );
        } catch (
            DomainException|InvalidArgumentException $exception
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'finance' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'finance.contracts.show',
                $contract
            )
            ->with(
                'success',
                'Contrato financeiro criado com sucesso.'
            );
    }

    public function show(
        BillingContract $billingContract,
    ): View {
        $billingContract->load('items');

        $invoices = Invoice::query()
            ->where(
                'billing_contract_id',
                $billingContract->id
            )
            ->latest('id')
            ->limit(20)
            ->get();

        return view('finance.contracts.show', [
            'contract' => $billingContract,
            'invoices' => $invoices,
            'financeEnabled' =>
                $this->financeEnabled(),
        ]);
    }

    public function activate(
        BillingContract $billingContract,
        ActivateBillingContract $action,
    ): RedirectResponse {
        $this->authorizeWrite();

        try {
            $action->handle(
                $billingContract->id,
                auth()->id(),
            );
        } catch (DomainException $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }

        return back()->with(
            'success',
            'Contrato financeiro ativado.'
        );
    }

    public function suspend(
        BillingContract $billingContract,
        SuspendBillingContract $action,
    ): RedirectResponse {
        $this->authorizeWrite();

        try {
            $action->handle(
                $billingContract->id,
                auth()->id(),
            );
        } catch (DomainException $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }

        return back()->with(
            'success',
            'Contrato financeiro suspenso.'
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
