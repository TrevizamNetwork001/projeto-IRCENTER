@extends('layouts.app')

@section('title', 'Validação RPKI — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Resource Public Key Infrastructure
            </div>

            <h1>Validação RPKI</h1>

            <p>
                Acompanhe a autorização dos prefixos em relação ao ASN de origem
                e aos ROAs conhecidos pelo IRCENTER.
            </p>
        </div>
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="rpki-summary-grid">
        <article class="rpki-summary-card">
            <span>Total de prefixos</span>
            <strong>{{ $summary['total'] }}</strong>
        </article>

        <article class="rpki-summary-card is-valid">
            <span>Válidos</span>
            <strong>{{ $summary['valid'] }}</strong>
        </article>

        <article class="rpki-summary-card is-invalid">
            <span>Inválidos</span>
            <strong>{{ $summary['invalid'] }}</strong>
        </article>

        <article class="rpki-summary-card is-not-found">
            <span>Sem ROA</span>
            <strong>{{ $summary['not_found'] }}</strong>
        </article>

        <article class="rpki-summary-card is-error">
            <span>Erros</span>
            <strong>{{ $summary['error'] }}</strong>
        </article>

        <article class="rpki-summary-card is-unchecked">
            <span>Não verificados</span>
            <strong>{{ $summary['unchecked'] }}</strong>
        </article>
    </section>

    <section class="panel">
        <form
            class="filter-bar"
            method="GET"
            action="{{ route('rpki.index') }}"
        >
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar prefixo, cliente ou ASN"
                >
            </div>

            <select class="filter-select" name="version">
                <option value="">IPv4 e IPv6</option>
                <option value="4" @selected($version === 4)>IPv4</option>
                <option value="6" @selected($version === 6)>IPv6</option>
            </select>

            <select class="filter-select" name="client">
                <option value="">Todos os clientes</option>

                @foreach ($clients as $client)
                    <option
                        value="{{ $client->id }}"
                        @selected((string) $clientId === (string) $client->id)
                    >
                        {{ $client->displayName() }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="status">
                <option value="all" @selected($status === 'all')>
                    Todos os estados
                </option>

                <option value="valid" @selected($status === 'valid')>
                    Válido
                </option>

                <option value="invalid" @selected($status === 'invalid')>
                    Inválido
                </option>

                <option value="not_found" @selected($status === 'not_found')>
                    Sem ROA
                </option>

                <option value="error" @selected($status === 'error')>
                    Erro
                </option>

                <option value="unchecked" @selected($status === 'unchecked')>
                    Não verificado
                </option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if (
                $search !== ''
                || $status !== 'all'
                || $version > 0
                || $clientId > 0
            )
                <a class="button button-ghost" href="{{ route('rpki.index') }}">
                    Limpar
                </a>
            @endif
        </form>

        @if ($prefixes->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="certificate" size="25"/>
                </div>

                <div>
                    <strong>Nenhum prefixo encontrado</strong>
                    <span>
                        Cadastre prefixos ou altere os filtros da consulta.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prefixo</th>
                            <th>Cliente</th>
                            <th>ASN de origem</th>
                            <th>Estado RPKI</th>
                            <th>Motivo</th>
                            <th>Última validação</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($prefixes as $prefix)
                            @php
                                $validation = $prefix->latestRpkiValidation;
                                $rpkiStatus = $validation?->status ?? 'unchecked';
                            @endphp

                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link table-mono"
                                        href="{{ route('prefixes.show', $prefix) }}"
                                    >
                                        {{ $prefix->prefix }}
                                    </a>

                                    <span class="table-secondary-text">
                                        IPv{{ $prefix->ip_version }}
                                    </span>
                                </td>

                                <td>
                                    <a
                                        class="table-action-link"
                                        href="{{ route('clients.show', $prefix->client) }}"
                                    >
                                        {{ $prefix->client->displayName() }}
                                    </a>
                                </td>

                                <td>
                                    @if ($prefix->autonomousSystem)
                                        <a
                                            class="table-action-link table-mono"
                                            href="{{ route(
                                                'autonomous-systems.show',
                                                $prefix->autonomousSystem
                                            ) }}"
                                        >
                                            {{ $prefix->autonomousSystem->formattedAsn() }}
                                        </a>
                                    @else
                                        <span class="text-muted">Sem ASN</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="rpki-status-pill is-{{ $rpkiStatus }}">
                                        @if ($validation)
                                            {{ $validation->displayStatus() }}
                                        @else
                                            Não verificado
                                        @endif
                                    </span>
                                </td>

                                <td>
                                    {{ match ($validation?->reason) {
                                        'matching_roa' => 'ROA compatível',
                                        'origin_asn_mismatch' => 'ASN divergente',
                                        'max_length_exceeded' => 'Comprimento excedido',
                                        'no_covering_roa' => 'Nenhum ROA cobrindo',
                                        'missing_origin_asn' => 'ASN não vinculado',
                                        'invalid_prefix' => 'Prefixo inválido',
                                        null => '—',
                                        default => $validation->reason,
                                    } }}
                                </td>

                                <td>
                                    {{ $validation?->checked_at?->format('d/m/Y H:i') ?: 'Nunca' }}
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route('rpki.history', $prefix) }}"
                                        >
                                            Histórico
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

                                                <button
                                                    class="table-action-button"
                                                    type="submit"
                                                >
                                                    Validar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($prefixes->hasPages())
                <div class="pagination-simple">
                    @if ($prefixes->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $prefixes->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $prefixes->currentPage() }}
                        de {{ $prefixes->lastPage() }}
                    </span>

                    @if ($prefixes->hasMorePages())
                        <a href="{{ $prefixes->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
