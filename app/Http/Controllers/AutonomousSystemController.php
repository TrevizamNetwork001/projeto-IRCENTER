<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutonomousSystemRequest;
use App\Http\Requests\UpdateAutonomousSystemRequest;
use App\Models\AutonomousSystem;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutonomousSystemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $clientId = $request->integer('client');

        $autonomousSystems = AutonomousSystem::query()
            ->with('client')
            ->when($search !== '', function ($query) use ($search): void {
                $asnSearch = preg_replace('/^AS/i', '', $search);

                $query->where(function ($query) use ($search, $asnSearch): void {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('noc_email', 'ilike', "%{$search}%")
                        ->orWhereHas('client', function ($query) use ($search): void {
                            $query
                                ->where('legal_name', 'ilike', "%{$search}%")
                                ->orWhere('trade_name', 'ilike', "%{$search}%");
                        });

                    if (ctype_digit((string) $asnSearch)) {
                        $query->orWhere('asn', (int) $asnSearch);
                    }
                });
            })
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->orderByDesc('active')
            ->orderBy('asn')
            ->paginate(20)
            ->withQueryString();

        return view('autonomous-systems.index', [
            'autonomousSystems' => $autonomousSystems,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'search' => $search,
            'status' => $status,
            'clientId' => $clientId,
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('autonomous-systems.create', [
            'autonomousSystem' => new AutonomousSystem([
                'country' => 'BR',
                'rir' => 'LACNIC',
                'active' => true,
            ]),
            'clients' => Client::query()
                ->where('active', true)
                ->orderBy('legal_name')
                ->get(),
        ]);
    }

    public function store(
        StoreAutonomousSystemRequest $request
    ): RedirectResponse {
        $autonomousSystem = AutonomousSystem::create(
            $request->validated()
        );

        return redirect()
            ->route('autonomous-systems.show', $autonomousSystem)
            ->with('success', 'ASN cadastrado com sucesso.');
    }

    public function show(
        AutonomousSystem $autonomousSystem
    ): View {
        $autonomousSystem->load('client');

        return view('autonomous-systems.show', [
            'autonomousSystem' => $autonomousSystem,
        ]);
    }

    public function edit(
        AutonomousSystem $autonomousSystem
    ): View {
        $this->authorizeAdministrator();

        return view('autonomous-systems.edit', [
            'autonomousSystem' => $autonomousSystem,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
        ]);
    }

    public function update(
        UpdateAutonomousSystemRequest $request,
        AutonomousSystem $autonomousSystem
    ): RedirectResponse {
        $oldValues = $autonomousSystem->getOriginal();

        $autonomousSystem->update($request->validated());

        app(AuditService::class)->record(
            'updated',
            $autonomousSystem,
            $oldValues,
            $autonomousSystem->fresh()->getAttributes(),
            'AS'.$autonomousSystem->asn
        );

        return redirect()
            ->route('autonomous-systems.show', $autonomousSystem)
            ->with('success', 'ASN atualizado com sucesso.');
    }

    public function destroy(
        AutonomousSystem $autonomousSystem
    ): RedirectResponse {
        $this->authorizeAdministrator();

        $oldValues = $autonomousSystem->getAttributes();

        $autonomousSystem->delete();

        app(AuditService::class)->record(
            'deleted',
            $autonomousSystem,
            $oldValues,
            null,
            'AS'.$autonomousSystem->asn
        );

        return redirect()
            ->route('autonomous-systems.index')
            ->with('success', 'ASN removido com sucesso.');
    }

    public function toggleActive(
        AutonomousSystem $autonomousSystem
    ): RedirectResponse {
        $this->authorizeAdministrator();

        $oldValues = $autonomousSystem->getOriginal();

        $autonomousSystem->update([
            'active' => ! $autonomousSystem->active,
        ]);

        app(AuditService::class)->record(
            $autonomousSystem->active ? 'activated' : 'deactivated',
            $autonomousSystem,
            $oldValues,
            $autonomousSystem->fresh()->getAttributes(),
            'AS'.$autonomousSystem->asn
        );

        return back()->with(
            'success',
            $autonomousSystem->active
                ? 'ASN ativado com sucesso.'
                : 'ASN desativado com sucesso.'
        );
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
