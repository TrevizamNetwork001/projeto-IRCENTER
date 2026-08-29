@extends('layouts.app')

@section('title', 'Detalhe da auditoria | IRCENTER')

@section('content')
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">Auditoria</span>
            <h1>{{ $auditLog->resource_label }}</h1>
            <p>
                {{ $auditLog->resource_type }}
                ·
                {{ $auditLog->action }}
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('audit.index') }}"
        >
            Voltar
        </a>
    </div>

    <section class="detail-grid">
        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Evento</span>
                    <h2>Informações gerais</h2>
                </div>
            </header>

            <dl class="detail-list">
                <div>
                    <dt>Usuário</dt>
                    <dd>{{ $auditLog->user?->name ?? 'Sistema' }}</dd>
                </div>

                <div>
                    <dt>Data</dt>
                    <dd>
                        {{ $auditLog->created_at?->format(
                            'd/m/Y H:i:s'
                        ) }}
                    </dd>
                </div>

                <div>
                    <dt>Ação</dt>
                    <dd>{{ $auditLog->action }}</dd>
                </div>

                <div>
                    <dt>Recurso</dt>
                    <dd>
                        {{ $auditLog->resource_type }}
                        #{{ $auditLog->resource_id }}
                    </dd>
                </div>

                <div>
                    <dt>IP</dt>
                    <dd>{{ $auditLog->ip_address ?? '—' }}</dd>
                </div>

                <div>
                    <dt>User-Agent</dt>
                    <dd>{{ $auditLog->user_agent ?? '—' }}</dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Alterações</span>
                <h2>Comparação de valores</h2>
            </div>

            @if (count($diffRows) > 0)
                <span class="panel-status">
                    {{ collect($diffRows)->where('changed', true)->count() }}
                    de {{ count($diffRows) }} campo(s) alterado(s)
                </span>
            @endif
        </header>

        @if (count($diffRows) === 0)
            <div class="empty-state">
                <strong>Sem valores registrados</strong>
                <span>
                    Este evento não possui estado anterior nem novo
                    associado.
                </span>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table audit-diff-table">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Antes</th>
                            <th>Depois</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($diffRows as $row)
                            <tr class="{{ $row['changed'] ? 'is-changed' : '' }}">
                                <td>{{ $row['label'] }}</td>
                                <td class="table-mono">{{ $row['old'] }}</td>
                                <td class="table-mono">{{ $row['new'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
