@extends('layouts.app')

@section('title', 'Incidentes — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Roteamento e segurança
            </div>

            <h1>Incidentes</h1>

            <p>
                Registro e acompanhamento de ocorrências de roteamento,
                disponibilidade e segurança da rede.
            </p>
        </div>

        @if (auth()->user()->canOperate())
            <a
                class="button button-primary"
                href="{{ route('routing-incidents.create') }}"
            >
                Novo incidente
            </a>
        @endif
    </section>

    @if (session('success'))
        <div
            class="alert-success"
            data-auto-dismiss="5000"
        >
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        <form
            class="filter-bar incident-filter-bar"
            method="GET"
            action="{{ route('routing-incidents.index') }}"
        >
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar referência, título, cliente ou descrição"
                >
            </div>

            <select class="filter-select" name="status">
                <option value="open" @selected($status === 'open')>
                    Incidentes abertos
                </option>

                <option value="" @selected($status === '')>
                    Todos os status
                </option>

                @foreach (\App\Models\RoutingIncident::statuses() as $value)
                    <option
                        value="{{ $value }}"
                        @selected($status === $value)
                    >
                        {{ (new \App\Models\RoutingIncident([
                            'status' => $value
                        ]))->statusLabel() }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="severity">
                <option value="">Todas as severidades</option>

                @foreach (\App\Models\RoutingIncident::severities() as $value)
                    <option
                        value="{{ $value }}"
                        @selected($severity === $value)
                    >
                        {{ (new \App\Models\RoutingIncident([
                            'severity' => $value
                        ]))->severityLabel() }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="type">
                <option value="">Todos os tipos</option>

                @foreach (\App\Models\RoutingIncident::types() as $value)
                    <option
                        value="{{ $value }}"
                        @selected($type === $value)
                    >
                        {{ (new \App\Models\RoutingIncident([
                            'type' => $value
                        ]))->typeLabel() }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="client">
                <option value="">Todos os clientes</option>

                @foreach ($clients as $client)
                    <option
                        value="{{ $client->id }}"
                        @selected(
                            (string) $clientId
                            === (string) $client->id
                        )
                    >
                        {{ $client->displayName() }}
                    </option>
                @endforeach
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if (
                $search !== ''
                || $status !== 'open'
                || $severity !== ''
                || $type !== ''
                || $clientId > 0
            )
                <a
                    class="button button-ghost"
                    href="{{ route('routing-incidents.index') }}"
                >
                    Limpar
                </a>
            @endif
        </form>

        @if ($incidents->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="incident" size="25"/>
                </div>

                <div>
                    <strong>Nenhum incidente encontrado</strong>

                    <span>
                        Não existem ocorrências correspondentes aos filtros.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table incident-table">
                    <thead>
                        <tr>
                            <th>Incidente</th>
                            <th>Tipo</th>
                            <th>Severidade</th>
                            <th>Status</th>
                            <th>Cliente / Recurso</th>
                            <th>Responsável</th>
                            <th>Detectado</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link"
                                        href="{{ route(
                                            'routing-incidents.show',
                                            $incident
                                        ) }}"
                                    >
                                        {{ $incident->title }}
                                    </a>

                                    <span
                                        class="table-secondary-text table-mono"
                                    >
                                        {{ $incident->reference }}
                                    </span>
                                </td>

                                <td>{{ $incident->typeLabel() }}</td>

                                <td>
                                    <span
                                        class="incident-severity-pill
                                            is-{{ $incident->severity }}"
                                    >
                                        {{ $incident->severityLabel() }}
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="incident-status-pill
                                            is-{{ $incident->status }}"
                                    >
                                        {{ $incident->statusLabel() }}
                                    </span>
                                </td>

                                <td>
                                    @if ($incident->client)
                                        <a
                                            class="table-action-link"
                                            href="{{ route(
                                                'clients.show',
                                                $incident->client
                                            ) }}"
                                        >
                                            {{ $incident->client
                                                ->displayName() }}
                                        </a>
                                    @else
                                        —
                                    @endif

                                    @if ($incident->prefix)
                                        <span
                                            class="table-secondary-text table-mono"
                                        >
                                            {{ $incident->prefix->prefix }}
                                        </span>
                                    @elseif ($incident->autonomousSystem)
                                        <span
                                            class="table-secondary-text table-mono"
                                        >
                                            {{ $incident->autonomousSystem
                                                ->formattedAsn() }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    {{ $incident->assignee?->name
                                        ?? 'Não atribuído' }}
                                </td>

                                <td>
                                    {{ $incident->detected_at
                                        ?->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route(
                                                'routing-incidents.show',
                                                $incident
                                            ) }}"
                                        >
                                            Abrir
                                        </a>

                                        @if (auth()->user()->canOperate())
                                            <a
                                                class="table-action-link"
                                                href="{{ route(
                                                    'routing-incidents.edit',
                                                    $incident
                                                ) }}"
                                            >
                                                Editar
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($incidents->hasPages())
                <div class="pagination-simple">
                    @if ($incidents->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $incidents->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $incidents->currentPage() }}
                        de {{ $incidents->lastPage() }}
                    </span>

                    @if ($incidents->hasMorePages())
                        <a href="{{ $incidents->nextPageUrl() }}">
                            Próxima
                        </a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
