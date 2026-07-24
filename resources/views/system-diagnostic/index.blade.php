@extends('layouts.app')

@section('title', 'Diagnóstico do sistema — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Administração
            </div>

            <h1>Diagnóstico do sistema</h1>

            <p>
                Estado atual dos componentes essenciais do IRCENTER.
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('system-diagnostic.index') }}"
        >
            Atualizar
        </a>
    </section>

    <section class="diagnostic-check-grid">
        @foreach ($checks as $check)
            <article
                class="panel diagnostic-check-card
                    {{ $check['healthy']
                        ? 'is-healthy'
                        : 'is-failed' }}"
            >
                <div>
                    <span>{{ $check['name'] }}</span>

                    <strong>
                        {{ $check['healthy']
                            ? 'Operacional'
                            : 'Indisponível' }}
                    </strong>
                </div>

                <small>
                    {{ $check['detail'] }}
                    · {{ $check['duration_ms'] }} ms
                </small>
            </article>
        @endforeach
    </section>

    <section class="details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Execução</span>
                    <h2>Filas e automações</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Jobs aguardando na fila</dt>
                    <dd>
                        {{ $pendingJobs !== null
                            ? $pendingJobs
                            : 'Indisponível' }}
                    </dd>
                </div>

                <div>
                    <dt>Jobs falhos</dt>
                    <dd>{{ $failedJobs }}</dd>
                </div>

                <div>
                    <dt>Última automação</dt>
                    <dd>
                        @if ($latestAutomationRun)
                            {{ $latestAutomationRun->automation }}
                            · {{ $latestAutomationRun->status }}
                            · {{ $latestAutomationRun->created_at
                                ?->format('d/m/Y H:i:s') }}
                        @else
                            Nenhuma execução registrada
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Última integração externa</dt>
                    <dd>
                        @if ($latestIntegrationRun)
                            {{ $latestIntegrationRun->integration?->name
                                ?? 'Integração removida' }}
                            · {{ $latestIntegrationRun->status }}
                            · {{ $latestIntegrationRun->created_at
                                ?->format('d/m/Y H:i:s') }}
                        @else
                            Nenhuma execução registrada
                        @endif
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Ambiente</span>
                    <h2>Versões e configuração</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Ambiente</dt>
                    <dd>{{ $environment }}</dd>
                </div>

                <div>
                    <dt>Debug</dt>
                    <dd>{{ $debugEnabled ? 'Ativado' : 'Desativado' }}</dd>
                </div>

                <div>
                    <dt>PHP</dt>
                    <dd>{{ $phpVersion }}</dd>
                </div>

                <div>
                    <dt>Laravel</dt>
                    <dd>{{ $laravelVersion }}</dd>
                </div>

                <div>
                    <dt>Prontidão pública</dt>
                    <dd>
                        <a
                            class="table-action-link"
                            href="{{ route('health.ready') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            /health/ready
                        </a>
                    </dd>
                </div>
            </dl>
        </article>
    </section>
@endsection
