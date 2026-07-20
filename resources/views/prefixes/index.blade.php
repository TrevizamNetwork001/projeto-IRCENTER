@extends('layouts.app')

@section(
    'title',
    ($version === 6 ? 'Prefixos IPv6' : ($version === 4 ? 'Prefixos IPv4' : 'Prefixos'))
    .' — IRCENTER'
)

@section('content')
    @php
        $versionLabel = $version === 6
            ? 'IPv6'
            : ($version === 4 ? 'IPv4' : 'IP');

        $createVersion = $version === 6 ? 6 : 4;
    @endphp

    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Recursos de numeração
            </div>

            <h1>Prefixos {{ $versionLabel }}</h1>

            <p>
                Blocos {{ $versionLabel }} vinculados a clientes e sistemas
                autônomos gerenciados pelo IRCENTER.
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <a
                class="button button-primary"
                href="{{ route('prefixes.create', ['version' => $createVersion]) }}"
            >
                Novo prefixo {{ $versionLabel }}
            </a>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        <form
            class="filter-bar"
            method="GET"
            action="{{ $version === 6
                ? route('prefixes.ipv6')
                : route('prefixes.ipv4') }}"
        >
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar por prefixo, cliente, ASN ou finalidade"
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
                <option value="all" @selected($status === 'all')>
                    Todos os status
                </option>

                <option value="active" @selected($status === 'active')>
                    Ativos
                </option>

                <option value="inactive" @selected($status === 'inactive')>
                    Inativos
                </option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if ($search !== '' || $status !== 'all' || $clientId > 0)
                <a
                    class="button button-ghost"
                    href="{{ $version === 6
                        ? route('prefixes.ipv6')
                        : route('prefixes.ipv4') }}"
                >
                    Limpar
                </a>
            @endif
        </form>

        @if ($prefixes->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon :name="$version === 6 ? 'ipv6' : 'ipv4'" size="25"/>
                </div>

                <div>
                    <strong>Nenhum prefixo {{ $versionLabel }} encontrado</strong>

                    <span>
                        Cadastre o primeiro bloco ou altere os filtros de busca.
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
                            <th>ASN</th>
                            <th>Finalidade</th>
                            <th>Alocação</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($prefixes as $prefix)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link table-mono"
                                        href="{{ route('prefixes.show', $prefix) }}"
                                    >
                                        {{ $prefix->prefix }}
                                    </a>

                                    @if ($prefix->description)
                                        <span class="table-secondary-text">
                                            {{ $prefix->description }}
                                        </span>
                                    @endif
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
                                        —
                                    @endif
                                </td>

                                <td>{{ $prefix->purpose ?: '—' }}</td>

                                <td>
                                    <span class="allocation-pill">
                                        {{ match ($prefix->allocation_status) {
                                            'allocated' => 'Alocado',
                                            'assigned' => 'Designado',
                                            'reserved' => 'Reservado',
                                            'legacy' => 'Legado',
                                            'available' => 'Disponível',
                                            default => $prefix->allocation_status,
                                        } }}
                                    </span>
                                </td>

                                <td>
                                    <span class="status-pill {{ $prefix->active ? 'is-active' : 'is-inactive' }}">
                                        {{ $prefix->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route('prefixes.show', $prefix) }}"
                                        >
                                            Abrir
                                        </a>

                                        @if (auth()->user()->isAdministrator())
                                            <a
                                                class="table-action-link"
                                                href="{{ route('prefixes.edit', $prefix) }}"
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
