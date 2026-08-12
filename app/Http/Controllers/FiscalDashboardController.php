<?php

namespace App\Http\Controllers;

use App\Modules\Fiscal\Models\FiscalCustomerProfile;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Models\FiscalServiceProfile;
use Illuminate\View\View;

final class FiscalDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('fiscal.dashboard', [
            'environment' => config('finance_fiscal.fiscal.environment', 'homologation'),
            'issuerCount' => FiscalIssuerProfile::query()->where('active', true)->count(),
            'customerCount' => FiscalCustomerProfile::query()->count(),
            'serviceCount' => FiscalServiceProfile::query()->where('active', true)->count(),
            'documentCount' => FiscalDocument::query()->count(),
            'pendingCount' => FiscalDocument::query()->whereIn('status', ['ready', 'processing', 'rejected'])->count(),
            'documents' => FiscalDocument::query()->latest('id')->limit(10)->get(),
        ]);
    }
}
