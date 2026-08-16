<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFiscalIssuerProfileRequest;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Services\FiscalIssuerReadinessService;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class FiscalIssuerProfileController extends Controller
{
    public function edit(FiscalIssuerReadinessService $readiness): View
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $profile = FiscalIssuerProfile::query()->orderByDesc('active')->orderBy('id')->first();

        return view('fiscal.issuer.edit', [
            'profile' => $profile,
            'readiness' => $readiness->evaluate($profile),
        ]);
    }

    public function update(UpdateFiscalIssuerProfileRequest $request, DomainAudit $audit): RedirectResponse
    {
        $connection = DB::connection('finance_fiscal');
        $profile = $connection->transaction(function () use ($request, $audit, $connection): FiscalIssuerProfile {
            if ($connection->getDriverName() === 'pgsql') {
                $connection->select('select pg_advisory_xact_lock(?)', [118243901]);
            }
            $profile = FiscalIssuerProfile::query()->lockForUpdate()->orderByDesc('active')->orderBy('id')->first();
            $wasActive = $profile?->active === true;
            $created = $profile === null;
            $data = $request->validated();

            if ($data['active']) {
                FiscalIssuerProfile::query()->where('active', true)->when($profile, fn ($query) => $query->whereKeyNot($profile->id))->update(['active' => false]);
            }

            $profile ??= new FiscalIssuerProfile();
            $profile->fill($data)->save();
            $action = $created ? 'fiscal.issuer.created' : 'fiscal.issuer.updated';
            $audit->record('fiscal', $action, $request->user()->id, FiscalIssuerProfile::class, $profile->id, ['active' => $profile->active]);
            if (! $wasActive && $profile->active) {
                $audit->record('fiscal', 'fiscal.issuer.activated', $request->user()->id, FiscalIssuerProfile::class, $profile->id);
            } elseif ($wasActive && ! $profile->active) {
                $audit->record('fiscal', 'fiscal.issuer.deactivated', $request->user()->id, FiscalIssuerProfile::class, $profile->id);
            }

            return $profile;
        }, 3);

        return redirect()->route('fiscal.issuer.edit')->with('success', $profile->active ? 'Emitente fiscal salvo e ativado.' : 'Emitente fiscal salvo como inativo.');
    }
}
