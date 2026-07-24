@extends('layouts.app')

@section('title', $integration->name.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                {{ $integration->typeLabel() }}
            </div>

            <h1>{{ $integration->name }}</h1>

            <p class="integration-heading-endpoint">
                {{ $integration->endpoint }}
            </p>
        </div>

        <div class="page-actions">
            <a
                class="button button-secondary"
                href="{{ route(
                    'external-integrations.edit',
                    $integration
                ) }}"
            >
                Editar
            </a>

            <form
                method="POST"
                action="{{ route(
                    'external-integrations.test',
                    $integration
                ) }}"
            >
                @csrf

                <button
                    class="button button-primary"
                    type="submit"
                    @disabled(! $integration->active)
                >
                    Testar conectividade
                </button>
            </form>
        </div>
    </section>

    @if (session('success'))
        <div class="alert-success" data-auto-dismiss="5000">
            {{ session('success') }}
        </div>
    @endif

    <section class="integration-overview-grid">
        <article class="panel integration-summary-card">
            <span>Status cadastral</span>
            <strong>{{ $integration->active ? 'Ativa' : 'Desativada' }}</strong>
        </article>

        <article class="panel integration-summary-card">
            <span>Último teste</span>
            <strong>
                {{ $integration->last_tested_at
                    ?->format('d/m/Y H:i')
                    ?? 'Nunca' }}
            </strong>
        </article>

        <article class="panel integration-summary-card">
            <span>Resultado</span>
            <strong>
                {{ match ($integration->last_test_status) {
                    'success' => 'Sucesso',
                    'failed' => 'Falhou',
                    'blocked' => 'Bloqueado',
                    'running' => 'Executando',
                    'pending' => 'Pendente',
                    default => 'Não testada',
                } }}
            </strong>
        </article>

        <article class="panel integration-summary-card">
            <span>HTTP</span>
            <strong>
                {{ $integration->last_http_status ?: '—' }}
            </strong>
        </article>
    </section>

    <section class="details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Configuração</span>
                    <h2>Conectividade</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Tipo</dt>
                    <dd>{{ $integration->typeLabel() }}</dd>
                </div>

                <div>
                    <dt>Endpoint</dt>
                    <dd class="integration-detail-endpoint">
                        {{ $integration->endpoint }}
                    </dd>
                </div>

                <div>
                    <dt>Timeout</dt>
                    <dd>{{ $integration->timeout_seconds }} segundos</dd>
                </div>

                <div>
                    <dt>Autenticação</dt>
                    <dd>{{ $integration->authenticationLabel() }}</dd>
                </div>

                <div>
                    <dt>Usuário</dt>
                    <dd>{{ $integration->username ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Segredo</dt>
                    <dd>
                        {{ $integration->secret !== null
                            ? 'Configurado e protegido'
                            : 'Não configurado' }}
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Governança</span>
                    <h2>Cadastro e atualização</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Cadastrada por</dt>
                    <dd>{{ $integration->creator?->name ?? 'Sistema' }}</dd>
                </div>

                <div>
                    <dt>Última alteração por</dt>
                    <dd>{{ $integration->updater?->name ?? 'Sistema' }}</dd>
                </div>

                <div>
                    <dt>Criada em</dt>
                    <dd>
                        {{ $integration->created_at?->format(
                            'd/m/Y H:i'
                        ) }}
                    </dd>
                </div>

                <div>
                    <dt>Atualizada em</dt>
                    <dd>
                        {{ $integration->updated_at?->format(
                            'd/m/Y H:i'
                        ) }}
                    </dd>
                </div>

                <div>
                    <dt>Último erro</dt>
                    <dd>
                        {{ $integration->last_error
                            ?: 'Nenhum erro registrado.' }}
                    </dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Fila e histórico</span>
                <h2>Execuções recentes</h2>
            </div>

            <span class="panel-status">
                {{ $integration->runs->count() }}
            </span>
        </header>

        @if ($integration->runs->isEmpty())
            <div class="empty-state">
                <strong>Nenhum teste executado</strong>

                <span>
                    Use “Testar conectividade” para enviar a primeira
                    verificação à fila.
                </span>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Solicitado</th>
                            <th>Usuário</th>
                            <th>Status</th>
                            <th>HTTP</th>
                            <th>IP resolvido</th>
                            <th>Duração</th>
                            <th>Tipo de resposta</th>
                            <th>Erro</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($integration->runs as $run)
                            <tr>
                                <td>
                                    {{ $run->created_at?->format(
                                        'd/m/Y H:i:s'
                                    ) }}
                                </td>

                                <td>
                                    {{ $run->requester?->name ?? 'Sistema' }}
                                </td>

                                <td>
                                    <span
                                        class="integration-status-pill
                                            is-{{ $run->status }}"
                                    >
                                        {{ match ($run->status) {
                                            'success' => 'Sucesso',
                                            'failed' => 'Falhou',
                                            'blocked' => 'Bloqueado',
                                            'running' => 'Executando',
                                            default => 'Pendente',
                                        } }}
                                    </span>
                                </td>

                                <td>{{ $run->http_status ?: '—' }}</td>

                                <td class="table-mono">
                                    {{ $run->resolved_ip ?: '—' }}
                                </td>

                                <td>
                                    {{ $run->duration_ms !== null
                                        ? $run->duration_ms.' ms'
                                        : '—' }}
                                </td>

                                <td>
                                    {{ $run->response_content_type
                                        ?: '—' }}
                                </td>

                                <td class="integration-run-error">
                                    {{ $run->error_message ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
