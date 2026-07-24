@extends('layouts.app')

@section('title', 'Relatórios — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Análise e exportação
            </div>

            <h1>Relatórios</h1>

            <p>
                Consulte indicadores operacionais e exporte os dados
                cadastrados no IRCENTER.
            </p>
        </div>
    </section>

    <section class="report-summary-grid">
        @foreach ([
            ['label' => 'Clientes', 'value' => $resourceTotals['clients'], 'icon' => 'clients'],
            ['label' => 'ASNs', 'value' => $resourceTotals['asns'], 'icon' => 'asn'],
            ['label' => 'Prefixos IPv4', 'value' => $resourceTotals['ipv4'], 'icon' => 'ipv4'],
            ['label' => 'Prefixos IPv6', 'value' => $resourceTotals['ipv6'], 'icon' => 'ipv6'],
        ] as $metric)
            <article class="panel report-summary-card">
                <span class="report-summary-icon">
                    <x-icon :name="$metric['icon']" size="21"/>
                </span>

                <div>
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ $metric['value'] }}</strong>
                </div>
            </article>
        @endforeach
    </section>

    <section class="panel report-filter-panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Incidentes</span>
                <h2>Filtros do relatório operacional</h2>
            </div>
        </header>

        <form
            class="report-filter-grid"
            method="GET"
            action="{{ route('reports.index') }}"
        >
            <div class="field-group">
                <label for="client">Cliente</label>

                <select
                    id="client"
                    class="form-control"
                    name="client"
                >
                    <option value="">Todos os clientes</option>

                    @foreach ($clients as $client)
                        <option
                            value="{{ $client->id }}"
                            @selected(
                                (string) $filters['client']
                                === (string) $client->id
                            )
                        >
                            {{ $client->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field-group">
                <label for="status">Status</label>

                <select
                    id="status"
                    class="form-control"
                    name="status"
                >
                    <option value="">Todos os status</option>

                    @foreach (\App\Models\RoutingIncident::statuses() as $value)
                        <option
                            value="{{ $value }}"
                            @selected($filters['status'] === $value)
                        >
                            {{ (new \App\Models\RoutingIncident([
                                'status' => $value,
                            ]))->statusLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field-group">
                <label for="severity">Severidade</label>

                <select
                    id="severity"
                    class="form-control"
                    name="severity"
                >
                    <option value="">Todas as severidades</option>

                    @foreach (\App\Models\RoutingIncident::severities() as $value)
                        <option
                            value="{{ $value }}"
                            @selected($filters['severity'] === $value)
                        >
                            {{ (new \App\Models\RoutingIncident([
                                'severity' => $value,
                            ]))->severityLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field-group">
                <label for="type">Tipo</label>

                <select
                    id="type"
                    class="form-control"
                    name="type"
                >
                    <option value="">Todos os tipos</option>

                    @foreach (\App\Models\RoutingIncident::types() as $value)
                        <option
                            value="{{ $value }}"
                            @selected($filters['type'] === $value)
                        >
                            {{ (new \App\Models\RoutingIncident([
                                'type' => $value,
                            ]))->typeLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field-group">
                <label for="date_from">Data inicial</label>

                <input
                    id="date_from"
                    class="form-control"
                    name="date_from"
                    type="date"
                    value="{{ $filters['date_from'] }}"
                >
            </div>

            <div class="field-group">
                <label for="date_to">Data final</label>

                <input
                    id="date_to"
                    class="form-control"
                    name="date_to"
                    type="date"
                    value="{{ $filters['date_to'] }}"
                >
            </div>

            <div class="report-filter-actions">
                <button class="button button-primary" type="submit">
                    Atualizar relatório
                </button>

                <a
                    class="button button-ghost"
                    href="{{ route('reports.index') }}"
                >
                    Limpar
                </a>
            </div>
        </form>
    </section>

    <section class="report-incident-grid">
        @foreach ([
            ['label' => 'Incidentes filtrados', 'value' => $incidentTotals['total']],
            ['label' => 'Em aberto', 'value' => $incidentTotals['open']],
            ['label' => 'Críticos', 'value' => $incidentTotals['critical']],
            ['label' => 'Resolvidos', 'value' => $incidentTotals['resolved']],
        ] as $metric)
            <article class="panel report-incident-card">
                <span>{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <section class="panel report-export-panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">CSV</span>
                <h2>Exportações disponíveis</h2>
            </div>
        </header>

        <div class="report-export-grid">
            @foreach ([
                'clients' => [
                    'title' => 'Clientes',
                    'description' => 'Cadastro, contatos e totais de recursos.',
                    'icon' => 'clients',
                ],
                'autonomous-systems' => [
                    'title' => 'ASNs',
                    'description' => 'Sistemas autônomos e contatos de NOC.',
                    'icon' => 'asn',
                ],
                'prefixes' => [
                    'title' => 'Prefixos',
                    'description' => 'Blocos IPv4 e IPv6 com seus vínculos.',
                    'icon' => 'ipv4',
                ],
                'incidents' => [
                    'title' => 'Incidentes',
                    'description' => 'Ocorrências conforme os filtros selecionados.',
                    'icon' => 'incident',
                ],
            ] as $key => $export)
                <article class="report-export-card">
                    <span class="report-export-icon">
                        <x-icon :name="$export['icon']" size="22"/>
                    </span>

                    <div>
                        <strong>{{ $export['title'] }}</strong>
                        <p>{{ $export['description'] }}</p>
                    </div>

                    <a
                        class="button button-secondary"
                        href="{{ route(
                            'reports.export',
                            ['report' => $key] + $filters
                        ) }}"
                    >
                        Exportar CSV
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Ocorrências</span>
                <h2>Incidentes recentes</h2>
            </div>

            <span class="panel-status">
                {{ $recentIncidents->count() }}
            </span>
        </header>

        @if ($recentIncidents->isEmpty())
            <div class="empty-state">
                <strong>Nenhum incidente encontrado</strong>
                <span>
                    Ajuste os filtros ou registre uma nova ocorrência.
                </span>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Referência</th>
                            <th>Incidente</th>
                            <th>Cliente</th>
                            <th>Severidade</th>
                            <th>Status</th>
                            <th>Detectado</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($recentIncidents as $incident)
                            <tr>
                                <td class="table-mono">
                                    <a
                                        class="table-action-link"
                                        href="{{ route(
                                            'routing-incidents.show',
                                            $incident
                                        ) }}"
                                    >
                                        {{ $incident->reference }}
                                    </a>
                                </td>

                                <td>{{ $incident->title }}</td>

                                <td>
                                    {{ $incident->client?->displayName()
                                        ?? '—' }}
                                </td>

                                <td>{{ $incident->severityLabel() }}</td>
                                <td>{{ $incident->statusLabel() }}</td>

                                <td>
                                    {{ $incident->detected_at
                                        ?->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
