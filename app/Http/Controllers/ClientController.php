<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $clients = Client::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('legal_name', 'ilike', "%{$search}%")
                        ->orWhere('trade_name', 'ilike', "%{$search}%")
                        ->orWhere('document', 'ilike', "%{$search}%")
                        ->orWhere('client_code', 'ilike', "%{$search}%")
                        ->orWhere('contract_number', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('city', 'ilike', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->orderByDesc('active')
            ->orderBy('legal_name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('clients.create', [
            'client' => new Client([
                'country' => 'BR',
                'active' => true,
            ]),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $generateContract = (bool) (
            $data['generate_contract_number'] ?? false
        );

        unset($data['generate_contract_number']);

        $client = DB::transaction(function () use (
            $data,
            $generateContract
        ): Client {
            $client = Client::create($data);

            $client->forceFill([
                'client_code' => sprintf(
                    'CLI-%06d',
                    $client->id
                ),
                'contract_number' => $generateContract
                    ? sprintf(
                        'CTR-%s-%06d',
                        now()->format('Y'),
                        $client->id
                    )
                    : $client->contract_number,
            ])->save();

            return $client->refresh();
        });

        app(AuditService::class)->record(
            'created',
            $client,
            null,
            $client->getAttributes(),
            $client->legal_name
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Cliente cadastrado com sucesso.');
    }

    public function show(Client $client): View
    {
        return view('clients.show', [
            'client' => $client->load([
                'contacts' => fn ($query) => $query
                    ->orderBy('type')
                    ->orderByDesc('active')
                    ->orderByDesc('is_primary')
                    ->orderBy('name'),
            ]),
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorizeAdministrator();

        return view('clients.edit', [
            'client' => $client,
        ]);
    }

    public function update(
        UpdateClientRequest $request,
        Client $client
    ): RedirectResponse {
        $oldValues = $client->getOriginal();
        $data = $request->validated();
        $generateContract = (bool) (
            $data['generate_contract_number'] ?? false
        );

        unset($data['generate_contract_number']);

        if ($generateContract) {
            $data['contract_number'] = sprintf(
                'CTR-%s-%06d',
                now()->format('Y'),
                $client->id
            );
        }

        $client->update($data);

        app(AuditService::class)->record(
            'updated',
            $client,
            $oldValues,
            $client->fresh()->getAttributes(),
            $client->legal_name
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Cliente atualizado com sucesso.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $oldValues = $client->getAttributes();

        $client->delete();

        app(AuditService::class)->record(
            'deleted',
            $client,
            $oldValues,
            null,
            $client->legal_name
        );

        return redirect()
            ->route('clients.index')
            ->with('success', 'Cliente removido com sucesso.');
    }

    public function toggleActive(Client $client): RedirectResponse
    {
        $this->authorizeAdministrator();

        $oldValues = $client->getOriginal();

        $client->update([
            'active' => ! $client->active,
        ]);

        app(AuditService::class)->record(
            $client->active ? 'activated' : 'deactivated',
            $client,
            $oldValues,
            $client->fresh()->getAttributes(),
            $client->legal_name
        );

        return back()->with(
            'success',
            $client->active
                ? 'Cliente ativado com sucesso.'
                : 'Cliente desativado com sucesso.'
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
