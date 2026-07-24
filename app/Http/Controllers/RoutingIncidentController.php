<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoutingIncidentRequest;
use App\Http\Requests\StoreRoutingIncidentUpdateRequest;
use App\Http\Requests\UpdateRoutingIncidentRequest;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoutingIncidentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'open'));
        $severity = trim((string) $request->query('severity', ''));
        $type = trim((string) $request->query('type', ''));
        $clientId = $request->integer('client');

        $incidents = RoutingIncident::query()
            ->with([
                'client',
                'autonomousSystem',
                'prefix',
                'assignee',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('reference', 'ilike', "%{$search}%")
                        ->orWhere('title', 'ilike', "%{$search}%")
                        ->orWhere('summary', 'ilike', "%{$search}%")
                        ->orWhere('external_reference', 'ilike', "%{$search}%")
                        ->orWhereHas(
                            'client',
                            fn ($query) => $query
                                ->where('legal_name', 'ilike', "%{$search}%")
                                ->orWhere(
                                    'trade_name',
                                    'ilike',
                                    "%{$search}%"
                                )
                        );
                });
            })
            ->when(
                $status === 'open',
                fn ($query) => $query->whereNotIn(
                    'status',
                    [
                        RoutingIncident::STATUS_RESOLVED,
                        RoutingIncident::STATUS_CLOSED,
                    ]
                )
            )
            ->when(
                in_array($status, RoutingIncident::statuses(), true),
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                in_array(
                    $severity,
                    RoutingIncident::severities(),
                    true
                ),
                fn ($query) => $query->where(
                    'severity',
                    $severity
                )
            )
            ->when(
                in_array($type, RoutingIncident::types(), true),
                fn ($query) => $query->where('type', $type)
            )
            ->when(
                $clientId > 0,
                fn ($query) => $query->where(
                    'client_id',
                    $clientId
                )
            )
            ->orderByRaw(
                "case severity
                    when 'critical' then 1
                    when 'high' then 2
                    when 'medium' then 3
                    else 4
                end"
            )
            ->latest('detected_at')
            ->paginate(20)
            ->withQueryString();

        return view('routing-incidents.index', [
            'incidents' => $incidents,
            'clients' => Client::query()
                ->orderBy('legal_name')
                ->get(),
            'search' => $search,
            'status' => $status,
            'severity' => $severity,
            'type' => $type,
            'clientId' => $clientId,
        ]);
    }

    public function create(): View
    {
        $this->authorizeOperator();

        return view('routing-incidents.create', [
            'incident' => new RoutingIncident([
                'status' => RoutingIncident::STATUS_OPEN,
                'severity' => RoutingIncident::SEVERITY_MEDIUM,
                'detected_at' => now(),
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(
        StoreRoutingIncidentRequest $request
    ): RedirectResponse {
        $incident = DB::transaction(function () use ($request) {
            $incident = RoutingIncident::create([
                ...$request->validated(),
                'reference' => 'TEMP-'.uniqid(),
                'reported_by_user_id' => $request->user()->id,
            ]);

            $incident->update([
                'reference' => sprintf(
                    'INC-%s-%06d',
                    now()->format('Y'),
                    $incident->id
                ),
            ]);

            $incident->updates()->create([
                'user_id' => $request->user()->id,
                'kind' => 'created',
                'new_status' => $incident->status,
                'message' => 'Incidente registrado na plataforma.',
            ]);

            return $incident->refresh();
        });

        app(AuditService::class)->record(
            'created',
            $incident,
            null,
            $incident->getAttributes(),
            $incident->reference
        );

        return redirect()
            ->route('routing-incidents.show', $incident)
            ->with('success', 'Incidente registrado com sucesso.');
    }

    public function show(RoutingIncident $routingIncident): View
    {
        $routingIncident->load([
            'client',
            'autonomousSystem',
            'prefix',
            'reporter',
            'assignee',
            'updates.user',
        ]);

        return view('routing-incidents.show', [
            'incident' => $routingIncident,
        ]);
    }

    public function edit(RoutingIncident $routingIncident): View
    {
        $this->authorizeOperator();

        return view('routing-incidents.edit', [
            'incident' => $routingIncident,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        UpdateRoutingIncidentRequest $request,
        RoutingIncident $routingIncident
    ): RedirectResponse {
        $oldValues = $routingIncident->getOriginal();
        $oldStatus = $routingIncident->status;
        $data = $request->validated();

        if (
            $oldStatus !== $data['status']
            && $data['status'] === RoutingIncident::STATUS_INVESTIGATING
            && $routingIncident->acknowledged_at === null
        ) {
            $data['acknowledged_at'] = now();
        }

        if (
            $oldStatus !== $data['status']
            && $data['status'] === RoutingIncident::STATUS_RESOLVED
        ) {
            $data['resolved_at'] = now();
        }

        if (
            $oldStatus !== $data['status']
            && $data['status'] === RoutingIncident::STATUS_CLOSED
        ) {
            $data['closed_at'] = now();
        }

        $routingIncident->update($data);

        if ($oldStatus !== $routingIncident->status) {
            $routingIncident->updates()->create([
                'user_id' => $request->user()->id,
                'kind' => 'status_change',
                'old_status' => $oldStatus,
                'new_status' => $routingIncident->status,
                'message' => sprintf(
                    'Status alterado de %s para %s.',
                    $this->statusLabel($oldStatus),
                    $routingIncident->statusLabel()
                ),
            ]);
        }

        app(AuditService::class)->record(
            'updated',
            $routingIncident,
            $oldValues,
            $routingIncident->fresh()->getAttributes(),
            $routingIncident->reference
        );

        return redirect()
            ->route('routing-incidents.show', $routingIncident)
            ->with('success', 'Incidente atualizado com sucesso.');
    }

    public function storeUpdate(
        StoreRoutingIncidentUpdateRequest $request,
        RoutingIncident $routingIncident
    ): RedirectResponse {
        $update = $routingIncident->updates()->create([
            'user_id' => $request->user()->id,
            'kind' => 'note',
            'message' => $request->validated('message'),
        ]);

        app(AuditService::class)->record(
            'timeline_updated',
            $routingIncident,
            null,
            [
                'update_id' => $update->id,
                'kind' => $update->kind,
            ],
            $routingIncident->reference
        );

        return back()->with(
            'success',
            'Atualização adicionada à linha do tempo.'
        );
    }

    private function formOptions(): array
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
                ->with(['client', 'autonomousSystem'])
                ->orderBy('prefix')
                ->get(),
            'users' => User::query()
                ->where('active', true)
                ->whereIn('role', [
                    User::ROLE_ADMIN,
                    User::ROLE_OPERATOR,
                ])
                ->orderBy('name')
                ->get(),
        ];
    }

    private function authorizeOperator(): void
    {
        abort_unless(
            auth()->user()?->canOperate() === true,
            403
        );
    }

    private function statusLabel(string $status): string
    {
        return (new RoutingIncident([
            'status' => $status,
        ]))->statusLabel();
    }
}
