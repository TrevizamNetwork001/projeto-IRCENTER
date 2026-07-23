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

    <section class="audit-values-grid">
        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Antes</span>
                    <h2>Valores anteriores</h2>
                </div>
            </header>

            <pre class="code-preview">{{ json_encode(
                $auditLog->old_values,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?: 'Nenhum valor anterior.' }}</pre>
        </article>

        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Depois</span>
                    <h2>Novos valores</h2>
                </div>
            </header>

            <pre class="code-preview">{{ json_encode(
                $auditLog->new_values,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?: 'Nenhum valor novo.' }}</pre>
        </article>
    </section>
@endsection
