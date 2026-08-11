<?php

namespace App\Http\Controllers;

use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use Illuminate\View\View;
use App\Support\BusinessClock;

final class FinanceDashboardController extends Controller
{
    public function __invoke(BusinessClock $clock): View
    {
        $openInvoiceTotal = Invoice::query()
            ->where('status', Invoice::STATUS_OPEN)
            ->sum('total');

        $activeConfigurations = BillingContract::query()
            ->where('status', BillingContract::STATUS_ACTIVE)
            ->with(['items' => fn ($query) => $query->where('active', true)])
            ->latest('id')->get()->unique('core_client_id')->values();

        return view('finance.dashboard', [
            'financeEnabled' =>
                (bool) config(
                    'finance_fiscal.finance.enabled',
                    false
                ),

            'paymentProvider' =>
                (string) config(
                    'finance_fiscal.finance.payment_provider',
                    'fake'
                ),

            'efiEnvironment' =>
                (string) config(
                    'finance_fiscal.providers.efi.environment',
                    'homologation'
                ),

            'openInvoiceTotal' =>
                $openInvoiceTotal,

            'overdueTotal' => Invoice::query()
                ->where('status', Invoice::STATUS_OPEN)
                ->whereDate('due_on', '<', $clock->today()->toDateString())
                ->sum('total'),

            'paidInPeriod' => Invoice::query()
                ->where('status', Invoice::STATUS_PAID)
                ->whereDate('updated_at', '>=', $clock->today()->startOfMonth()->toDateString())
                ->sum('total'),

            'clientsRecurring' => $activeConfigurations->count(),
            'upcomingConfigurations' => $activeConfigurations,
            'attentionCount' => Charge::query()->whereIn('status', [
                Charge::STATUS_SUBMISSION_UNKNOWN,
                Charge::STATUS_FAILED,
                Charge::STATUS_SUBMITTING,
            ])->count(),

            'recentInvoices' =>
                Invoice::query()->with(['charges' => fn ($query) => $query->latest('id')])
                    ->latest('id')
                    ->limit(5)
                    ->get(),
        ]);
    }
}
