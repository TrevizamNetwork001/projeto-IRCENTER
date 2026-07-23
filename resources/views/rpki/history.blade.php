@extends('layouts.app')

@section('title', 'Histórico RPKI '.$prefix->prefix.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Histórico de validação RPKI
            </div>

            <h1 class="table-mono">{{ $prefix->prefix }}</h1>

            <p>
                Resultados registrados para o prefixo IPv{{ $prefix->ip_version }}.
            </p>
        </div>

        <div class="page-actions">
            <a
                class="button button-secondary"
                href="{{ route('prefixes.show', $prefix) }}"
            >
                Abrir prefixo
            </a>

            @if (auth()->user()->isAdministrator())
                <form
                    method="POST"
                    action="{{ route(
                        'prefixes.rpki-validation.store',
                        $prefix
                    ) }}"
                >
                    @csrf

                    <button class="button button-primary" type="submit">
                        Executar validação
                    </button>
                </form>
            @endif
        </div>
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Inventário</span>
                    <h2>Dados do prefixo</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Cliente</dt>
                    <dd>
                        <a
                            class="inline-link"
                            href="{{ route('clients.show', $prefix->client) }}"
                        >
                            {{ $prefix->client->displayName() }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt>ASN de origem</dt>
                    <dd>
                        @if ($prefix->autonomousSystem)
                            <a
                                class="inline-link table-mono"
                                href="{{ route(
                                    'autonomous-systems.show',
                                    $prefix->autonomousSystem
                                ) }}"
                            >
                                {{ $prefix->autonomousSystem->formattedAsn() }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Versão</dt>
                    <dd>IPv{{ $prefix->ip_version }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Histórico</span>
                    <h2>Resumo</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Total de verificações</dt>
                    <dd>{{ $validations->total() }}</dd>
                </div>

                <div>
                    <dt>Última verificação</dt>
                    <dd>
                        {{ $validations->first()?->checked_at?->format('d/m/Y H:i') ?: 'Nunca' }}
                    </dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Resultados</span>
                <h2>Validações registradas</h2>
            </div>
        </header>

        @if ($validations->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="certificate" size="25"/>
                </div>

                <div>
                    <strong>Nenhuma validação registrada</strong>
                    <span>Execute a primeira validação deste prefixo.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Estado</th>
                            <th>ASN validado</th>
                            <th>Motivo</th>
                            <th>ROAs encontrados</th>
                            <th>ROA associado</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($validations as $validation)
                            <tr>
                                <td>
                                    {{ $validation->checked_at->format('d/m/Y H:i:s') }}
                                </td>

                                <td>
                                    <span class="rpki-status-pill is-{{ $validation->status }}">
                                        {{ $validation->displayStatus() }}
                                    </span>
                                </td>

                                <td class="table-mono">
                                    {{ $validation->validated_asn
                                        ? 'AS'.$validation->validated_asn
                                        : '—' }}
                                </td>

                                <td>
                                    {{ match ($validation->reason) {
                                        'matching_roa' => 'ROA compatível',
                                        'origin_asn_mismatch' => 'ASN divergente',
                                        'max_length_exceeded' => 'Comprimento excedido',
                                        'no_covering_roa' => 'Nenhum ROA cobrindo',
                                        'missing_origin_asn' => 'ASN não vinculado',
                                        'invalid_prefix' => 'Prefixo inválido',
                                        default => $validation->reason ?: '—',
                                    } }}
                                </td>

                                <td>{{ $validation->matching_roas_count }}</td>

                                <td>
                                    @if ($validation->roa)
                                        <span class="table-mono">
                                            {{ $validation->roa->prefix }}
                                            →
                                            {{ $validation->roa->formattedAsn() }}
                                        </span>

                                        <span class="table-secondary-text">
                                            maxLength {{ $validation->roa->max_length }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($validations->hasPages())
                <div class="pagination-simple">
                    @if ($validations->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $validations->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $validations->currentPage() }}
                        de {{ $validations->lastPage() }}
                    </span>

                    @if ($validations->hasMorePages())
                        <a href="{{ $validations->nextPageUrl() }}">
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
