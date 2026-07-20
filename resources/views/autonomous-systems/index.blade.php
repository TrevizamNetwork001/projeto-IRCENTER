@extends('layouts.app')

@section('title', 'ASNs — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Sistemas autônomos
            </div>

            <h1>ASNs</h1>

            <p>
                Inventário de sistemas autônomos vinculados aos clientes do IRCENTER.
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <a class="button button-primary" href="{{ route('autonomous-systems.create') }}">
                Novo ASN
            </a>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        <form class="filter-bar" method="GET" action="{{ route('autonomous-systems.index') }}">
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar por ASN, nome, cliente ou e-mail do NOC"
                >
            </div>

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
                <option value="all" @selected($status === 'all')>Todos os status</option>
                <option value="active" @selected($status === 'active')>Ativos</option>
                <option value="inactive" @selected($status === 'inactive')>Inativos</option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if ($search !== '' || $status !== 'all' || $clientId > 0)
                <a class="button button-ghost" href="{{ route('autonomous-systems.index') }}">
                    Limpar
                </a>
            @endif
        </form>

        @if ($autonomousSystems->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="asn" size="25"/>
                </div>

                <div>
                    <strong>Nenhum ASN encontrado</strong>
                    <span>
                        Cadastre o primeiro sistema autônomo ou altere os filtros.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ASN</th>
                            <th>Nome</th>
                            <th>Cliente</th>
                            <th>RIR</th>
                            <th>País</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($autonomousSystems as $autonomousSystem)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link table-mono"
                                        href="{{ route('autonomous-systems.show', $autonomousSystem) }}"
                                    >
                                        {{ $autonomousSystem->formattedAsn() }}
                                    </a>
                                </td>

                                <td>{{ $autonomousSystem->name }}</td>

                                <td>
                                    <a
                                        class="table-action-link"
                                        href="{{ route('clients.show', $autonomousSystem->client) }}"
                                    >
                                        {{ $autonomousSystem->client->displayName() }}
                                    </a>
                                </td>

                                <td>{{ $autonomousSystem->rir ?: '—' }}</td>
                                <td>{{ $autonomousSystem->country }}</td>

                                <td>
                                    <span class="status-pill {{ $autonomousSystem->active ? 'is-active' : 'is-inactive' }}">
                                        {{ $autonomousSystem->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route('autonomous-systems.show', $autonomousSystem) }}"
                                        >
                                            Abrir
                                        </a>

                                        @if (auth()->user()->isAdministrator())
                                            <a
                                                class="table-action-link"
                                                href="{{ route('autonomous-systems.edit', $autonomousSystem) }}"
                                            >
                                                Editar
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($autonomousSystems->hasPages())
                <div class="pagination-simple">
                    @if ($autonomousSystems->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $autonomousSystems->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $autonomousSystems->currentPage() }}
                        de {{ $autonomousSystems->lastPage() }}
                    </span>

                    @if ($autonomousSystems->hasMorePages())
                        <a href="{{ $autonomousSystems->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
