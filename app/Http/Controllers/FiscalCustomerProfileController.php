<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFiscalCustomerProfileRequest;
use App\Models\Client;
use App\Modules\Fiscal\Models\FiscalCustomerProfile;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class FiscalCustomerProfileController extends Controller
{
    public function edit(Client $client): View
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $profile = FiscalCustomerProfile::query()->firstOrNew(['core_client_id' => $client->id]);
        return view('fiscal.customers.edit', compact('client', 'profile'));
    }

    public function update(UpdateFiscalCustomerProfileRequest $request, Client $client, DomainAudit $audit): RedirectResponse
    {
        $profile = FiscalCustomerProfile::query()->updateOrCreate(['core_client_id' => $client->id], $request->validated());
        $audit->record('fiscal', 'customer-profile.updated', $request->user()->id, FiscalCustomerProfile::class, $profile->id, ['core_client_id' => $client->id]);
        return redirect()->route('clients.show', $client)->with('success', 'Cadastro fiscal atualizado com sucesso.');
    }
}
