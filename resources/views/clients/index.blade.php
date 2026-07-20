@extends('layouts.app')

@section('title', 'Clientes — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Gestão de organizações
            </div>

            <h1>Clientes</h1>

            <p>
                Organizações vinculadas a ASNs, prefixos e demais recursos
                monitorados pelo IRCENTER.
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <a class="button button-primary" href="{{ route('clients.create') }}">
                Novo cliente
            </a>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        <form class="filter-bar" method="GET" action="{{ route('clients.index') }}">
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar por nome, documento, e-mail ou cidade"
                >
            </div>

            <select class="filter-select" name="status">
                <option value="all" @selected($status === 'all')>Todos os status</option>
                <option value="active" @selected($status === 'active')>Ativos</option>
                <option value="inactive" @selected($status === 'inactive')>Inativos</option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if ($search !== '' || $status !== 'all')
                <a class="button button-ghost" href="{{ route('clients.index') }}">
                    Limpar
                </a>
            @endif
        </form>

        @if ($clients->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="clients" size="25"/>
                </div>

                <div>
                    <strong>Nenhum cliente encontrado</strong>
                    <span>
                        Cadastre a primeira organização ou altere os filtros de busca.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th>Localidade</th>
                            <th>Contato</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($clients as $client)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link"
                                        href="{{ route('clients.show', $client) }}"
                                    >
                                        {{ $client->displayName() }}
                                    </a>

                                    @if ($client->trade_name)
                                        <span class="table-secondary-text">
                                            {{ $client->legal_name }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="table-mono">
                                        {{ $client->document ?: '—' }}
                                    </span>
                                </td>

                                <td>
                                    {{ collect([$client->city, $client->state])
                                        ->filter()
                                        ->implode(' / ') ?: '—' }}
                                </td>

                                <td>
                                    <span>{{ $client->email ?: '—' }}</span>

                                    @if ($client->phone)
                                        <span class="table-secondary-text">
                                            {{ $client->phone }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="status-pill {{ $client->active ? 'is-active' : 'is-inactive' }}">
                                        {{ $client->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route('clients.show', $client) }}"
                                        >
                                            Abrir
                                        </a>

                                        @if (auth()->user()->isAdministrator())
                                            <a
                                                class="table-action-link"
                                                href="{{ route('clients.edit', $client) }}"
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

            @if ($clients->hasPages())
                <div class="pagination-simple">
                    @if ($clients->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $clients->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $clients->currentPage() }}
                        de {{ $clients->lastPage() }}
                    </span>

                    @if ($clients->hasMorePages())
                        <a href="{{ $clients->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
