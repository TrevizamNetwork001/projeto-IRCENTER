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

            'contractsTotal' =>
                BillingContract::query()->count(),

            'contractsActive' =>
                BillingContract::query()
                    ->where(
                        'status',
                        BillingContract::STATUS_ACTIVE
                    )
                    ->count(),

            'invoicesOpen' =>
                Invoice::query()
                    ->where(
                        'status',
                        Invoice::STATUS_OPEN
                    )
                    ->count(),

            'openInvoiceTotal' =>
                $openInvoiceTotal,

            'chargesOpen' =>
                Charge::query()
                    ->where(
                        'status',
                        Charge::STATUS_OPEN
                    )
                    ->count(),

            'chargesUnknown' =>
                Charge::query()
                    ->where(
                        'status',
                        Charge::STATUS_SUBMISSION_UNKNOWN
                    )
                    ->count(),

            'overdueTotal' => Invoice::query()
                ->where('status', Invoice::STATUS_OPEN)
                ->whereDate('due_on', '<', $clock->today()->toDateString())
                ->sum('total'),

            'paidInPeriod' => Invoice::query()
                ->where('status', Invoice::STATUS_PAID)
                ->whereDate('updated_at', '>=', $clock->today()->startOfMonth()->toDateString())
                ->sum('total'),

            'recentContracts' =>
                BillingContract::query()
                    ->latest('id')
                    ->limit(5)
                    ->get(),

            'recentInvoices' =>
                Invoice::query()
                    ->latest('id')
                    ->limit(5)
                    ->get(),
        ]);
    }
}
