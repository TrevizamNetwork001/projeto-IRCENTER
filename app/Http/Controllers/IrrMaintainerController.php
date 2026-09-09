<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIrrMaintainerRequest;
use App\Http\Requests\UpdateIrrMaintainerRequest;
use App\Models\Client;
use App\Models\IrrMaintainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IrrMaintainerController extends Controller
{
    public function index(): View
    {
        return view('irr-maintainers.index', [
            'maintainers' => IrrMaintainer::query()
                ->with('client')
                ->withCount(['routes', 'asSets'])
                ->orderBy('mntner')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdministrator();

        return view('irr-maintainers.create', [
            'maintainer' => new IrrMaintainer,
            'clients' => $this->activeClients(),
        ]);
    }

    public function store(StoreIrrMaintainerRequest $request): RedirectResponse
    {
        $maintainer = IrrMaintainer::query()->create($request->validated());

        return redirect()
            ->route('irr-maintainers.show', $maintainer)
            ->with('success', 'Maintainer cadastrado com sucesso.');
    }

    public function show(IrrMaintainer $irrMaintainer): View
    {
        $irrMaintainer->load(['client', 'routes', 'asSets']);

        return view('irr-maintainers.show', [
            'maintainer' => $irrMaintainer,
        ]);
    }

    public function edit(IrrMaintainer $irrMaintainer): View
    {
        $this->authorizeAdministrator();

        return view('irr-maintainers.edit', [
            'maintainer' => $irrMaintainer,
            'clients' => $this->activeClients(),
        ]);
    }

    public function update(UpdateIrrMaintainerRequest $request, IrrMaintainer $irrMaintainer): RedirectResponse
    {
        $data = $request->validated();

        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $irrMaintainer->update($data);

        return redirect()
            ->route('irr-maintainers.show', $irrMaintainer)
            ->with('success', 'Maintainer atualizado com sucesso.');
    }

    private function activeClients(): \Illuminate\Database\Eloquent\Collection
    {
        return Client::query()->where('active', true)->orderBy('legal_name')->get();
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator() === true, 403);
    }
}
