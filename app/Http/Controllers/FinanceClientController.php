<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\BillingRecipientResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FinanceClientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $contractClientIds = BillingContract::query()
            ->distinct()->pluck('core_client_id');

        $clients = Client::query()
            ->where(fn ($query) => $query
                ->where('active', true)
                ->orWhereIn('id', $contractClientIds))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query
                    ->where('legal_name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('client_code', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")
            ))
            ->orderBy('legal_name')
            ->paginate(20)
            ->withQueryString();

        $contracts = BillingContract::query()
            ->whereIn('core_client_id', $clients->pluck('id'))
            ->with(['items' => fn ($query) => $query->where('active', true)])
            ->latest('id')->get()->groupBy('core_client_id');

        $rows = $clients->getCollection()->map(function (Client $client) use ($contracts): array {
            $clientContracts = $contracts->get($client->id, collect());
            $primary = $clientContracts->firstWhere('status', BillingContract::STATUS_ACTIVE)
                ?? $clientContracts->first();

            return [
                'client' => $client,
                'primary' => $primary,
                'contracts_count' => $clientContracts->count(),
                'active_count' => $clientContracts->where('status', BillingContract::STATUS_ACTIVE)->count(),
                'total' => $primary?->items->sum(
                    fn ($item) => (float) $item->quantity * (float) $item->unit_amount
                ) ?? 0,
            ];
        });

        return view('finance.clients.index', [
            'clients' => $clients,
            'rows' => $rows,
            'search' => $search,
            'financeEnabled' => (bool) config('finance_fiscal.finance.enabled', false),
        ]);
    }

    public function show(Client $client, BillingRecipientResolver $recipients): View
    {
        $contracts = BillingContract::query()
            ->where('core_client_id', $client->id)
            ->with('items')->latest('id')->get();
        $primary = $contracts->firstWhere('status', BillingContract::STATUS_ACTIVE)
            ?? $contracts->first();
        $previous = $primary
            ? $contracts->reject(fn ($contract) => $contract->is($primary))->values()
            : collect();
        $invoices = Invoice::query()
            ->where('core_client_id', $client->id)
            ->with(['charges' => fn ($query) => $query->latest('id')])
            ->latest('id')->limit(10)->get();

        return view('finance.clients.show', [
            'client' => $client,
            'primary' => $primary,
            'previous' => $previous,
            'invoices' => $invoices,
            'recipient' => $primary ? $recipients->resolve($primary) : null,
            'automationEnabled' => (bool) config('finance_fiscal.finance.automation_enabled', false),
            'financeEnabled' => (bool) config('finance_fiscal.finance.enabled', false),
        ]);
    }
}
