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
                    <span class="panel-eyebrow">Proteção de dados</span>
                    <h2>Backup e off-site</h2>
                </div>

                @if ($backupStatus)
                    <span class="panel-status {{
                        ($backupStatus['last_backup_status'] ?? '') === 'success'
                            ? ''
                            : 'is-warning'
                    }}">
                        {{ ($backupStatus['last_backup_status'] ?? '') === 'success'
                            ? 'Último backup OK'
                            : 'Falha no último backup' }}
                    </span>
                @endif
            </header>

            @if (! $backupStatus)
                <div class="empty-state">
                    <strong>Sem dados de backup</strong>
                    <span>
                        Nenhum backup foi registrado ainda ou o status
                        não está acessível a partir da aplicação.
                    </span>
                </div>
            @else
                <dl class="details-list">
                    <div>
                        <dt>Último backup</dt>
                        <dd>{{ $backupStatus['last_backup_at'] ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt>Tamanho</dt>
                        <dd>
                            {{ isset($backupStatus['last_backup_size'])
                                ? number_format(
                                    ((int) $backupStatus['last_backup_size']) / 1024,
                                    0
                                ).' KB'
                                : '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Checksum</dt>
                        <dd>
                            {{ ($backupStatus['checksum_valid'] ?? '') === 'yes'
                                ? 'Válido'
                                : 'Não confirmado' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Criptografia</dt>
                        <dd>
                            {{ strtoupper(
                                $backupStatus['encryption'] ?? 'none'
                            ) }}
                        </dd>
                    </div>

                    <div>
                        <dt>Retenção local</dt>
                        <dd>
                            {{ $backupStatus['retention_daily_days'] ?? 14 }}d
                            diário
                            ·
                            {{ $backupStatus['retention_weekly_days'] ?? 90 }}d
                            semanal
                            ·
                            {{ $backupStatus['retention_monthly_days'] ?? 730 }}d
                            mensal
                        </dd>
                    </div>

                    <div>
                        <dt>Off-site</dt>
                        <dd>
                            @if ($offsiteStatus)
                                {{ ($offsiteStatus['last_offsite_status'] ?? '') === 'success'
                                    ? 'Sincronizado'
                                    : 'Falha na sincronização' }}
                                ·
                                {{ $offsiteStatus['last_offsite_sync_at'] ?? '—' }}
                                ·
                                retenção
                                {{ $offsiteStatus['retention_days'] ?? '—' }}d
                            @else
                                Não configurado
                            @endif
                        </dd>
                    </div>
                </dl>

                <form
                    class="filter-bar backup-retention-form"
                    method="POST"
                    action="{{ route('system-diagnostic.backup-retention.update') }}"
                >
                    @csrf
                    @method('PUT')

                    <label>
                        Diário (dias)
                        <input
                            class="form-control"
                            type="number"
                            name="retention_daily_days"
                            min="1"
                            max="3650"
                            value="{{ old(
                                'retention_daily_days',
                                $retentionConfig['RETENTION_DAILY_DAYS'] ?? 14
                            ) }}"
                            required
                        >
                    </label>

                    <label>
                        Semanal (dias)
                        <input
                            class="form-control"
                            type="number"
                            name="retention_weekly_days"
                            min="1"
                            max="3650"
                            value="{{ old(
                                'retention_weekly_days',
                                $retentionConfig['RETENTION_WEEKLY_DAYS'] ?? 90
                            ) }}"
                            required
                        >
                    </label>

                    <label>
                        Mensal (dias)
                        <input
                            class="form-control"
                            type="number"
                            name="retention_monthly_days"
                            min="1"
                            max="3650"
                            value="{{ old(
                                'retention_monthly_days',
                                $retentionConfig['RETENTION_MONTHLY_DAYS'] ?? 730
                            ) }}"
                            required
                        >
                    </label>

                    <button class="button button-primary" type="submit">
                        Salvar retenção
                    </button>
                </form>

                @if ($errors->any())
                    <div class="alert-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert-success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if (count($backupHistory) > 0)
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tier</th>
                                    <th>Status</th>
                                    <th>Tamanho</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($backupHistory as $entry)
                                    <tr class="{{
                                        ($entry['status'] ?? '') === 'success'
                                            ? ''
                                            : 'is-failed-row'
                                    }}">
                                        <td>{{ $entry['at'] ?? '—' }}</td>

                                        <td>{{ $entry['tier'] ?? '—' }}</td>
                                        <td>{{ $entry['status'] ?? '—' }}</td>

                                        <td class="table-mono">
                                            {{ isset($entry['size'])
                                                ? number_format(
                                                    ((int) $entry['size']) / 1024,
                                                    0
                                                ).' KB'
                                                : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </article>

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
