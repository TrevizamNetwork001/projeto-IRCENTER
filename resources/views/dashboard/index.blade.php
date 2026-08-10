@extends('layouts.app')

@section('title', 'Dashboard — IRCENTER')

@section('content')
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        .resource-chart-ring {
            --resource-chart: {{ $distributionGradient }};
        }
    </style>

    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Central de operações
            </div>

            <h1>Visão geral do ambiente</h1>

            <p>
                Acompanhe clientes, sistemas autônomos, recursos e
                pendências operacionais do IRCENTER.
            </p>
        </div>

        <div class="page-heading-status">
            <span>Última atualização</span>
            <strong>{{ now()->format('d/m/Y H:i') }}</strong>
        </div>
    </section>

    <section class="metrics-grid">
        @foreach ($metrics as $metric)
            <a class="metric-card" href="{{ $metric['route'] }}">
                <div class="metric-card-header">
                    <div class="metric-icon {{ $metric['tone'] }}">
                        <x-icon :name="$metric['icon']"/>
                    </div>

                    <span class="metric-state">
                        {{ $metric['state'] }}
                    </span>
                </div>

                <div class="metric-value">{{ $metric['value'] }}</div>
                <div class="metric-label">{{ $metric['label'] }}</div>

                <div class="metric-footer">
                    <span>{{ $metric['description'] }}</span>
                    <x-icon name="chevron-right" size="16"/>
                </div>
            </a>
        @endforeach
    </section>

    <section class="dashboard-grid">
        <article class="panel panel-large">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Inventário</span>
                    <h2>Distribuição de recursos</h2>
                </div>

                <span class="panel-status">
                    {{ $totalResources }} recursos
                </span>
            </header>

            <div class="resource-overview">
                <div class="resource-chart">
                    <div class="resource-chart-ring">
                        <div>
                            <strong>{{ $totalResources }}</strong>
                            <span>Recursos</span>
                        </div>
                    </div>
                </div>

                <div class="resource-legend">
                    <div class="legend-item">
                        <span class="legend-color clients"></span>
                        <div>
                            <strong>Clientes</strong>
                            <span>Organizações cadastradas</span>
                        </div>
                        <b>{{ $totals['clients'] }}</b>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color asns"></span>
                        <div>
                            <strong>ASNs</strong>
                            <span>Sistemas autônomos</span>
                        </div>
                        <b>{{ $totals['asns'] }}</b>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color ipv4"></span>
                        <div>
                            <strong>IPv4</strong>
                            <span>Prefixos IPv4</span>
                        </div>
                        <b>{{ $totals['ipv4'] }}</b>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color ipv6"></span>
                        <div>
                            <strong>IPv6</strong>
                            <span>Prefixos IPv6</span>
                        </div>
                        <b>{{ $totals['ipv6'] }}</b>
                    </div>
                </div>
            </div>
        </article>

        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Operação</span>
                    <h2>Pendências operacionais</h2>
                </div>

                <span
                    class="panel-status {{ $operationalIssueTotal > 0
                        ? 'is-warning'
                        : 'is-ok' }}"
                >
                    {{ $operationalIssueTotal }}
                </span>
            </header>

            @if ($operationalIssueTotal === 0)
                <div class="empty-state dashboard-empty-compact">
                    <div class="empty-state-icon">
                        <x-icon name="shield" size="22"/>
                    </div>

                    <div>
                        <strong>Nenhuma pendência operacional</strong>
                        <span>
                            Os recursos cadastrados estão consistentes.
                        </span>
                    </div>
                </div>
            @else
                <div class="operation-issue-list">
                    @foreach ($operationalIssues as $issue)
                        <a
                            class="operation-issue-item"
                            href="{{ $issue['route'] }}"
                        >
                            <div
                                class="metric-icon {{ $issue['tone'] }}"
                            >
                                <x-icon
                                    :name="$issue['icon']"
                                    size="17"
                                />
                            </div>

                            <div class="operation-issue-details">
                                <strong>{{ $issue['label'] }}</strong>
                                <span>{{ $issue['description'] }}</span>
                            </div>

                            <b>{{ $issue['value'] }}</b>

                            <x-icon
                                name="chevron-right"
                                size="15"
                            />
                        </a>
                    @endforeach
                </div>
            @endif
        </article>

        @if ($isAdministrator)
            <article class="panel panel-wide">
                <header class="panel-header">
                    <div>
                        <span class="panel-eyebrow">Auditoria</span>
                        <h2>Atividade recente</h2>
                    </div>

                    <a
                        class="panel-link"
                        href="{{ route('audit.index') }}"
                    >
                        Ver auditoria
                    </a>
                </header>

                @if ($recentAuditLogs->isEmpty())
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <x-icon name="activity" size="24"/>
                        </div>

                        <div>
                            <strong>Nenhuma atividade registrada</strong>
                            <span>
                                As próximas alterações aparecerão aqui.
                            </span>
                        </div>
                    </div>
                @else
                    <div class="dashboard-activity-list">
                        @foreach ($recentAuditLogs as $log)
                            <a
                                class="dashboard-activity-item"
                                href="{{ route('audit.show', $log) }}"
                            >
                                <div class="dashboard-activity-marker">
                                    <x-icon name="activity" size="16"/>
                                </div>

                                <div class="dashboard-activity-content">
                                    <strong>
                                        {{ $log->resource_label
                                            ?? $log->resource_type }}
                                    </strong>

                                    <span>
                                        {{ $log->user?->name ?? 'Sistema' }}
                                        ·
                                        {{ $log->action }}
                                        ·
                                        {{ $log->resource_type }}
                                    </span>
                                </div>

                                <time datetime="{{ $log->created_at }}">
                                    {{ $log->created_at?->diffForHumans() }}
                                </time>

                                <x-icon
                                    name="chevron-right"
                                    size="15"
                                />
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>
        @endif
    </section>
@endsection
