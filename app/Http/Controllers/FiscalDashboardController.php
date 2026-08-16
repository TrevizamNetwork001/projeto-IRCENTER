<?php

namespace App\Http\Controllers;

use App\Modules\Fiscal\Models\FiscalCustomerProfile;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Models\FiscalServiceProfile;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Models\Client;

final class FiscalDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $competence = (string) $request->query('competence', '');
        $competenceTo = (string) $request->query('competence_to', '');
        $origin = (string) $request->query('origin', '');
        $search = trim((string) $request->query('search', ''));
        $clientIds = $search === '' ? null : Client::query()->where('legal_name', 'like', "%{$search}%")->orWhere('trade_name', 'like', "%{$search}%")->pluck('id');
        $documents = FiscalDocument::query()->when($status !== '', fn ($query) => $query->where('status', $status))->when($competence !== '', fn ($query) => $query->whereDate('competence_date', '>=', $competence.'-01'))->when($competenceTo !== '', fn ($query) => $query->whereDate('competence_date', '<=', date('Y-m-t', strtotime($competenceTo.'-01'))))->when($origin !== '', fn ($query) => $query->where('emission_origin', $origin))->when($clientIds !== null, fn ($query) => $query->whereIn('core_client_id', $clientIds))->latest('id')->paginate(20)->withQueryString();
        $clientNames = Client::query()->whereIn('id', $documents->pluck('core_client_id'))->get()->mapWithKeys(fn (Client $client) => [$client->id => $client->displayName()]);
        return view('fiscal.dashboard', [
            'environment' => config('finance_fiscal.fiscal.environment', 'homologation'),
            'issuerCount' => FiscalIssuerProfile::query()->where('active', true)->count(),
            'customerCount' => FiscalCustomerProfile::query()->count(),
            'serviceCount' => FiscalServiceProfile::query()->where('active', true)->count(),
            'documentCount' => FiscalDocument::query()->count(),
            'draftCount' => FiscalDocument::query()->where('status', 'draft')->count(),
            'readyCount' => FiscalDocument::query()->where('status', 'ready')->count(),
            'authorizedCount' => FiscalDocument::query()->where('status', 'authorized')->count(),
            'pendingCount' => FiscalDocument::query()->whereIn('status', ['ready', 'processing', 'rejected'])->count(),
            'documents' => $documents,
            'clientNames' => $clientNames,
            'filters' => compact('status', 'competence', 'competenceTo', 'origin', 'search'),
        ]);
    }
}
