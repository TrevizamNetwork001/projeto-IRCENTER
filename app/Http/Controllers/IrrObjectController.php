<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIrrObjectRequest;
use App\Http\Requests\UpdateIrrObjectRequest;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\IrrObject;
use App\Models\Prefix;
use App\Services\RpslTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IrrObjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $source = trim((string) $request->query('source', 'all'));
        $clientId = $request->integer('client');

        $objects = IrrObject::query()
            ->with([
                'client',
                'autonomousSystem',
                'prefix',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('object_key', 'ilike', "%{$search}%")
                            ->orWhere('description', 'ilike', "%{$search}%")
                            ->orWhere('maintainer', 'ilike', "%{$search}%")
                            ->orWhere('source', 'ilike', "%{$search}%")
                            ->orWhereHas(
                                'client',
                                function ($query) use ($search): void {
                                    $query
                                        ->where(
                                            'legal_name',
                                            'ilike',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'trade_name',
                                            'ilike',
                                            "%{$search}%"
                                        );
                                }
                            )
                            ->orWhereHas(
                                'autonomousSystem',
                                function ($query) use ($search): void {
                                    $asn = preg_replace(
                                        '/^AS/i',
                                        '',
                                        $search
                                    );

                                    $query->where(
                                        'name',
                                        'ilike',
                                        "%{$search}%"
                                    );

                                    if (ctype_digit((string) $asn)) {
                                        $query->orWhere('asn', (int) $asn);
                                    }
                                }
                            );
                    });
                }
            )
            ->when(
                in_array($type, IrrObject::TYPES, true),
                fn ($query) => $query->where('object_type', $type)
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                $source !== 'all',
                fn ($query) => $query->where('source', $source)
            )
            ->when(
                $clientId > 0,
                fn ($query) => $query->where('client_id', $clientId)
            )
            ->orderByDesc('active')
            ->orderBy('object_type')
            ->orderBy('object_key')
            ->paginate(20)
            ->withQueryString();

        return view('irr.index', [
            'objects' => $objects,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'sources' => IrrObject::query()
                ->select('source')
                ->distinct()
                ->orderBy('source')
                ->pluck('source'),
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'source' => $source,
            'clientId' => $clientId,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdministrator();

        $type = trim((string) $request->query('type', 'route'));

        if (! in_array($type, IrrObject::TYPES, true)) {
            $type = 'route';
        }

        return view('irr.create', [
            'irrObject' => new IrrObject([
                'object_type' => $type,
                'source' => 'LOCAL',
                'status' => 'active',
                'active' => true,
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(
        StoreIrrObjectRequest $request,
        RpslTemplateService $templates
    ): RedirectResponse {
        $data = $request->validated();
        $data = $this->prepareRpsl($data, $templates);

        $irrObject = IrrObject::query()->create($data);

        return redirect()
            ->route('irr-objects.show', $irrObject)
            ->with('success', 'Objeto IRR cadastrado com sucesso.');
    }

    public function show(IrrObject $irrObject): View
    {
        $irrObject->load([
            'client',
            'autonomousSystem',
            'prefix',
        ]);

        return view('irr.show', [
            'irrObject' => $irrObject,
        ]);
    }

    public function edit(IrrObject $irrObject): View
    {
        $this->authorizeAdministrator();

        return view('irr.edit', [
            'irrObject' => $irrObject,
            ...$this->formData(),
        ]);
    }

    public function update(
        UpdateIrrObjectRequest $request,
        IrrObject $irrObject,
        RpslTemplateService $templates
    ): RedirectResponse {
        $data = $request->validated();
        $data = $this->prepareRpsl($data, $templates);

        $irrObject->update($data);

        return redirect()
            ->route('irr-objects.show', $irrObject)
            ->with('success', 'Objeto IRR atualizado com sucesso.');
    }

    public function toggleActive(
        IrrObject $irrObject
    ): RedirectResponse {
        $this->authorizeAdministrator();

        $irrObject->update([
            'active' => ! $irrObject->active,
        ]);

        return back()->with(
            'success',
            $irrObject->active
                ? 'Objeto IRR ativado com sucesso.'
                : 'Objeto IRR desativado com sucesso.'
        );
    }

    public function destroy(IrrObject $irrObject): RedirectResponse
    {
        $this->authorizeAdministrator();

        $irrObject->delete();

        return redirect()
            ->route('irr-objects.index')
            ->with('success', 'Objeto IRR removido com sucesso.');
    }

    /**
     * @return array{
     *     clients: \Illuminate\Database\Eloquent\Collection,
     *     autonomousSystems: \Illuminate\Database\Eloquent\Collection,
     *     prefixes: \Illuminate\Database\Eloquent\Collection
     * }
     */
    private function formData(): array
    {
        return [
            'clients' => Client::query()
                ->where('active', true)
                ->orderBy('legal_name')
                ->get(),
            'autonomousSystems' => AutonomousSystem::query()
                ->with('client')
                ->where('active', true)
                ->orderBy('asn')
                ->get(),
            'prefixes' => Prefix::query()
                ->with('client')
                ->where('active', true)
                ->orderBy('ip_version')
                ->orderBy('prefix')
                ->get(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareRpsl(
        array $data,
        RpslTemplateService $templates
    ): array {
        $client = isset($data['client_id'])
            ? Client::query()->find($data['client_id'])
            : null;

        $autonomousSystem = isset($data['autonomous_system_id'])
            ? AutonomousSystem::query()->find(
                $data['autonomous_system_id']
            )
            : null;

        $prefix = isset($data['prefix_id'])
            ? Prefix::query()->find($data['prefix_id'])
            : null;

        $rawText = trim((string) ($data['raw_text'] ?? ''));

        if ($rawText === '') {
            $data['raw_text'] = $templates->generate(
                type: $data['object_type'],
                key: $data['object_key'],
                client: $client,
                autonomousSystem: $autonomousSystem,
                prefix: $prefix,
                maintainer: $data['maintainer'] ?? null,
                source: $data['source'],
                description: $data['description'] ?? null
            );
        }

        return $data;
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );
    }
}
