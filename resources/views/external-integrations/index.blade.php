@extends('layouts.app')

@section('title', 'Integrações externas — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Conectividade controlada
            </div>

            <h1>Integrações externas</h1>

            <p>
                Endpoints externos cadastrados com validação de segurança,
                credenciais protegidas e testes executados pela fila.
            </p>
        </div>

        <a
            class="button button-primary"
            href="{{ route('external-integrations.create') }}"
        >
            Nova integração
        </a>
    </section>

    @if (session('success'))
        <div class="alert-success" data-auto-dismiss="5000">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        @if ($integrations->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="integration" size="25"/>
                </div>

                <div>
                    <strong>Nenhuma integração cadastrada</strong>

                    <span>
                        Cadastre um endpoint HTTPS para iniciar os testes
                        controlados de conectividade.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Integração</th>
                            <th>Tipo</th>
                            <th>Endpoint</th>
                            <th>Autenticação</th>
                            <th>Último teste</th>
                            <th>Status</th>
                            <th>Execuções</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($integrations as $integration)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link"
                                        href="{{ route(
                                            'external-integrations.show',
                                            $integration
                                        ) }}"
                                    >
                                        {{ $integration->name }}
                                    </a>

                                    <span class="table-secondary-text">
                                        {{ $integration->active
                                            ? 'Ativa'
                                            : 'Desativada' }}
                                    </span>
                                </td>

                                <td>{{ $integration->typeLabel() }}</td>

                                <td>
                                    <span
                                        class="integration-endpoint"
                                        title="{{ $integration->endpoint }}"
                                    >
                                        {{ $integration->endpoint }}
                                    </span>
                                </td>

                                <td>
                                    {{ $integration
                                        ->authenticationLabel() }}
                                </td>

                                <td>
                                    {{ $integration->last_tested_at
                                        ?->format('d/m/Y H:i')
                                        ?? 'Nunca' }}
                                </td>

                                <td>
                                    <span
                                        class="integration-status-pill
                                            is-{{ $integration->last_test_status
                                                ?? 'untested' }}"
                                    >
                                        {{ match (
                                            $integration->last_test_status
                                        ) {
                                            'success' => 'Sucesso',
                                            'failed' => 'Falhou',
                                            'blocked' => 'Bloqueado',
                                            'running' => 'Executando',
                                            'pending' => 'Pendente',
                                            default => 'Não testada',
                                        } }}
                                    </span>
                                </td>

                                <td>{{ $integration->runs_count }}</td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route(
                                                'external-integrations.show',
                                                $integration
                                            ) }}"
                                        >
                                            Abrir
                                        </a>

                                        <a
                                            class="table-action-link"
                                            href="{{ route(
                                                'external-integrations.edit',
                                                $integration
                                            ) }}"
                                        >
                                            Editar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($integrations->hasPages())
                <div class="pagination-simple">
                    @if ($integrations->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $integrations->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $integrations->currentPage() }}
                        de {{ $integrations->lastPage() }}
                    </span>

                    @if ($integrations->hasMorePages())
                        <a href="{{ $integrations->nextPageUrl() }}">
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
