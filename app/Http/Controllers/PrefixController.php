<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrefixRequest;
use App\Http\Requests\UpdatePrefixRequest;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrefixController extends Controller
{
    public function ipv4(Request $request): View
    {
        return $this->index($request, 4);
    }

    public function ipv6(Request $request): View
    {
        return $this->index($request, 6);
    }

    public function index(Request $request, ?int $forcedVersion = null): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $clientId = $request->integer('client');
        $version = $forcedVersion ?: $request->integer('version');

        if (! in_array($version, [4, 6], true)) {
            $version = 0;
        }

        $prefixes = Prefix::query()
            ->with(['client', 'autonomousSystem'])
            ->when($version > 0, fn ($query) => $query->where(
                'ip_version',
                $version
            ))
            ->when($clientId > 0, fn ($query) => $query->where(
                'client_id',
                $clientId
            ))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('prefix', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('purpose', 'ilike', "%{$search}%")
                        ->orWhereHas('client', function ($query) use ($search): void {
                            $query
                                ->where('legal_name', 'ilike', "%{$search}%")
                                ->orWhere('trade_name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas(
                            'autonomousSystem',
                            function ($query) use ($search): void {
                                $asn = preg_replace('/^AS/i', '', $search);

                                $query->where('name', 'ilike', "%{$search}%");

                                if (ctype_digit((string) $asn)) {
                                    $query->orWhere('asn', (int) $asn);
                                }
                            }
                        );
                });
            })
            ->when($status === 'active', fn ($query) => $query->where(
                'active',
                true
            ))
            ->when($status === 'inactive', fn ($query) => $query->where(
                'active',
                false
            ))
            ->orderByDesc('active')
            ->orderBy('prefix')
            ->paginate(20)
            ->withQueryString();

        return view('prefixes.index', [
            'prefixes' => $prefixes,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'search' => $search,
            'status' => $status,
            'clientId' => $clientId,
            'version' => $version,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdministrator();

        $version = $request->integer('version');

        if (! in_array($version, [4, 6], true)) {
            $version = 4;
        }

        return view('prefixes.create', [
            'prefix' => new Prefix([
                'ip_version' => $version,
                'country' => 'BR',
                'rir' => 'LACNIC',
                'allocation_status' => 'allocated',
                'active' => true,
            ]),
            'clients' => Client::query()
                ->where('active', true)
                ->orderBy('legal_name')
                ->get(),
            'autonomousSystems' => AutonomousSystem::query()
                ->with('client')
                ->where('active', true)
                ->orderBy('asn')
                ->get(),
        ]);
    }

    public function store(StorePrefixRequest $request): RedirectResponse
    {
        $prefix = Prefix::create($request->validated());

        return redirect()
            ->route('prefixes.show', $prefix)
            ->with('success', 'Prefixo cadastrado com sucesso.');
    }

    public function show(Prefix $prefix): View
    {
        $prefix->load([
            'client',
            'autonomousSystem',
            'latestRpkiValidation.roa',
        ]);

        return view('prefixes.show', [
            'prefix' => $prefix,
        ]);
    }

    public function edit(Prefix $prefix): View
    {
        $this->authorizeAdministrator();

        return view('prefixes.edit', [
            'prefix' => $prefix,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'autonomousSystems' => AutonomousSystem::query()
                ->with('client')
                ->orderBy('asn')
                ->get(),
        ]);
    }

    public function update(
        UpdatePrefixRequest $request,
        Prefix $prefix
    ): RedirectResponse {
        $prefix->update($request->validated());

        return redirect()
            ->route('prefixes.show', $prefix)
            ->with('success', 'Prefixo atualizado com sucesso.');
    }

    public function destroy(Prefix $prefix): RedirectResponse
    {
        $this->authorizeAdministrator();

        $prefix->delete();

        return redirect()
            ->route(
                $prefix->isIpv4() ? 'prefixes.ipv4' : 'prefixes.ipv6'
            )
            ->with('success', 'Prefixo removido com sucesso.');
    }

    public function toggleActive(Prefix $prefix): RedirectResponse
    {
        $this->authorizeAdministrator();

        $prefix->update([
            'active' => ! $prefix->active,
        ]);

        return back()->with(
            'success',
            $prefix->active
                ? 'Prefixo ativado com sucesso.'
                : 'Prefixo desativado com sucesso.'
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
