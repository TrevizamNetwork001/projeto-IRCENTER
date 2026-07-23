@extends('layouts.app')

@section('title', 'Assistente IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Implantação guiada
            </div>

            <h1>Assistente IRR</h1>

            <p>
                Prepare os objetos e acompanhe cada envio até a conclusão
                do cadastro IRR.
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <a
                class="button button-primary"
                href="{{ route('irr-workflows.create') }}"
            >
                Iniciar implantação IRR
            </a>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        @if ($workflows->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="workflow" size="25"/>
                </div>

                <div>
                    <strong>Nenhuma implantação IRR iniciada</strong>

                    <span>
                        Selecione um cliente e um ASN para gerar o primeiro
                        pacote guiado.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Processo</th>
                            <th>Cliente</th>
                            <th>ASN</th>
                            <th>Fonte</th>
                            <th>Etapa atual</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($workflows as $workflow)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link"
                                        href="{{ route(
                                            'irr-workflows.show',
                                            $workflow
                                        ) }}"
                                    >
                                        {{ $workflow->name }}
                                    </a>

                                    <span class="table-secondary-text">
                                        Iniciado em
                                        {{ $workflow->started_at?->format('d/m/Y H:i') }}
                                    </span>
                                </td>

                                <td>
                                    {{ $workflow->client->displayName() }}
                                </td>

                                <td class="table-mono">
                                    {{ $workflow->autonomousSystem->formattedAsn() }}
                                </td>

                                <td>{{ $workflow->irr_source }}</td>

                                <td>
                                    {{ $workflow->current_step }} de 6
                                </td>

                                <td>
                                    <span class="workflow-status is-{{ $workflow->status }}">
                                        {{ match ($workflow->status) {
                                            'in_progress' => 'Em andamento',
                                            'completed' => 'Concluído',
                                            'cancelled' => 'Cancelado',
                                            default => $workflow->status,
                                        } }}
                                    </span>
                                </td>

                                <td>
                                    <a
                                        class="table-action-link"
                                        href="{{ route(
                                            'irr-workflows.show',
                                            $workflow
                                        ) }}"
                                    >
                                        Continuar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($workflows->hasPages())
                <div class="pagination-simple">
                    @if ($workflows->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $workflows->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $workflows->currentPage() }}
                        de {{ $workflows->lastPage() }}
                    </span>

                    @if ($workflows->hasMorePages())
                        <a href="{{ $workflows->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
