<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\BillingRecipientResolver;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FinanceWorkspaceController extends Controller
{
    public function __invoke(Request $request, BusinessClock $clock, BillingRecipientResolver $recipients): View
    {
        $search = trim((string) $request->query('search', ''));
        $contractClientIds = BillingContract::query()->distinct()->pluck('core_client_id');
        $clients = Client::query()->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $contractClientIds))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('legal_name', 'like', "%{$search}%")->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('client_code', 'like', "%{$search}%")))->orderBy('legal_name')->get();
        $contracts = BillingContract::query()->whereIn('core_client_id', $clients->pluck('id'))
            ->with('items')->latest('id')->get()->groupBy('core_client_id');
        $openCounts = Invoice::query()->whereIn('core_client_id', $clients->pluck('id'))
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIALLY_PAID])
            ->selectRaw('core_client_id, count(*) as aggregate')->groupBy('core_client_id')->pluck('aggregate', 'core_client_id');
        $rows = $clients->map(function (Client $client) use ($contracts, $openCounts): array {
            $all = $contracts->get($client->id, collect());
            $current = $all->firstWhere('status', BillingContract::STATUS_ACTIVE)
                ?? $all->firstWhere('status', BillingContract::STATUS_SUSPENDED);
            return ['client' => $client, 'current' => $current,
                'total' => $current?->items->where('active', true)->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_amount) ?? 0,
                'open_count' => (int) ($openCounts[$client->id] ?? 0)];
        });
        $requestedId = (int) $request->query('client', 0);
        $selectedRow = $requestedId > 0
            ? $rows->first(fn ($row) => $row['client']->id === $requestedId)
            : null;
        $client = $selectedRow['client'] ?? null;
        $allContracts = $client ? $contracts->get($client->id, collect()) : collect();
        $current = $selectedRow['current'] ?? null;

        return view('finance.workspace', [
            'financeEnabled' => (bool) config('finance_fiscal.finance.enabled', false),
            'automationEnabled' => (bool) config('finance_fiscal.finance.automation_enabled', false),
            'paymentProvider' => (string) config('finance_fiscal.finance.payment_provider', 'fake'),
            'efiEnvironment' => (string) config('finance_fiscal.providers.efi.environment', 'homologation'),
            'search' => $search, 'clientRows' => $rows, 'selectedClient' => $client, 'primary' => $current,
            'previous' => $current ? $allContracts->reject(fn ($contract) => $contract->is($current))->values() : $allContracts,
            'recipient' => $current ? $recipients->resolve($current) : null,
            'invoices' => $client ? Invoice::query()->where('core_client_id', $client->id)
                ->with(['charges' => fn ($query) => $query->latest('id')])->latest('id')->limit(10)->get() : collect(),
            'clientOpenTotal' => $client ? Invoice::query()->where('core_client_id', $client->id)->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIALLY_PAID])->sum('total') : 0,
            'clientPaidInMonth' => $client ? Invoice::query()->where('core_client_id', $client->id)->where('status', Invoice::STATUS_PAID)->whereDate('updated_at', '>=', $clock->today()->startOfMonth()->toDateString())->sum('total') : 0,
            'nextDueOn' => $client ? Invoice::query()->where('core_client_id', $client->id)->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIALLY_PAID])->whereDate('due_on', '>=', $clock->today()->toDateString())->min('due_on') : null,
            'openTotal' => Invoice::query()->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIALLY_PAID])->sum('total'),
            'overdueTotal' => Invoice::query()->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIALLY_PAID])->whereDate('due_on', '<', $clock->today()->toDateString())->sum('total'),
            'paidTotal' => Invoice::query()->where('status', Invoice::STATUS_PAID)->whereDate('updated_at', '>=', $clock->today()->startOfMonth()->toDateString())->sum('total'),
            'attentionCount' => Charge::query()->whereIn('status', [Charge::STATUS_SUBMISSION_UNKNOWN, Charge::STATUS_FAILED, Charge::STATUS_SUBMITTING])->count(),
        ]);
    }
}
