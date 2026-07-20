@extends('layouts.app')

@section('title', 'Dashboard — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Central de operações
            </div>

            <h1>Visão geral do ambiente</h1>

            <p>
                Acompanhe clientes, sistemas autônomos e recursos de numeração
                gerenciados pelo IRCENTER.
            </p>
        </div>

        <div class="page-heading-status">
            <span>Última atualização</span>
            <strong>{{ now()->format('d/m/Y H:i') }}</strong>
        </div>
    </section>

    <section class="metrics-grid">
        @foreach ($metrics as $metric)
            <article class="metric-card">
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
            </article>
        @endforeach
    </section>

    <section class="dashboard-grid">
        <article class="panel panel-large">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Inventário</span>
                    <h2>Distribuição de recursos</h2>
                </div>

                <span class="panel-status">Base inicial</span>
            </header>

            <div class="resource-overview">
                <div class="resource-chart">
                    <div class="resource-chart-ring">
                        <div>
                            <strong>0</strong>
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
                        <b>0</b>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color asns"></span>
                        <div>
                            <strong>ASNs</strong>
                            <span>Sistemas autônomos</span>
                        </div>
                        <b>0</b>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color ipv4"></span>
                        <div>
                            <strong>IPv4</strong>
                            <span>Prefixos IPv4</span>
                        </div>
                        <b>0</b>
                    </div>

                    <div class="legend-item">
                        <span class="legend-color ipv6"></span>
                        <div>
                            <strong>IPv6</strong>
                            <span>Prefixos IPv6</span>
                        </div>
                        <b>0</b>
                    </div>
                </div>
            </div>
        </article>

        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Saúde</span>
                    <h2>Estado da plataforma</h2>
                </div>
            </header>

            <div class="health-list">
                @foreach ($health as $item)
                    <div class="health-item">
                        <div class="health-icon">
                            <x-icon :name="$item['icon']" size="18"/>
                        </div>

                        <div class="health-details">
                            <strong>{{ $item['label'] }}</strong>
                            <span>{{ $item['description'] }}</span>
                        </div>

                        <span class="health-status is-ok">
                            {{ $item['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="panel panel-wide">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Atividade</span>
                    <h2>Eventos recentes</h2>
                </div>
            </header>

            <div class="empty-state">
                <div class="empty-state-icon">
                    <x-icon name="activity" size="24"/>
                </div>

                <div>
                    <strong>Nenhuma atividade registrada</strong>
                    <span>
                        Os eventos de cadastro, alterações e monitoramento serão
                        apresentados aqui.
                    </span>
                </div>
            </div>
        </article>
    </section>
@endsection
