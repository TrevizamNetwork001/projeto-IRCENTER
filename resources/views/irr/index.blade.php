@extends('layouts.app')

@section('title', 'Objetos IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Internet Routing Registry
            </div>

            <h1>Objetos IRR</h1>

            <p>
                Gerencie objetos route, route6, aut-num e maintainer
                vinculados ao inventário do IRCENTER.
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('irr-workflows.create') }}"
                >
                    Gerar pacote IRR
                </a>

                <a
                    class="button button-primary"
                    href="{{ route('irr-objects.create') }}"
                >
                    Novo objeto avançado
                </a>
            </div>
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
            action="{{ route('irr-objects.index') }}"
        >
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar objeto, cliente, ASN ou maintainer"
                >
            </div>

            <select class="filter-select" name="type">
                <option value="all">Todos os tipos</option>
                <option value="route" @selected($type === 'route')>Route</option>
                <option value="route6" @selected($type === 'route6')>Route6</option>
                <option value="aut-num" @selected($type === 'aut-num')>Aut-num</option>
                <option value="as-set" @selected($type === 'as-set')>AS-set</option>
                <option value="mntner" @selected($type === 'mntner')>Maintainer</option>
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

            <select class="filter-select" name="source">
                <option value="all">Todas as fontes</option>

                @foreach ($sources as $sourceOption)
                    <option
                        value="{{ $sourceOption }}"
                        @selected($source === $sourceOption)
                    >
                        {{ $sourceOption }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="status">
                <option value="all">Todos os status</option>
                <option value="active" @selected($status === 'active')>Ativo</option>
                <option value="inactive" @selected($status === 'inactive')>Inativo</option>
                <option value="pending" @selected($status === 'pending')>Pendente</option>
                <option value="deprecated" @selected($status === 'deprecated')>Descontinuado</option>
                <option value="error" @selected($status === 'error')>Erro</option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if (
                $search !== ''
                || $type !== 'all'
                || $status !== 'all'
                || $source !== 'all'
                || $clientId > 0
            )
                <a
                    class="button button-ghost"
                    href="{{ route('irr-objects.index') }}"
                >
                    Limpar
                </a>
            @endif
        </form>

        @if ($objects->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="registry" size="25"/>
                </div>

                <div>
                    <strong>Nenhum objeto IRR encontrado</strong>
                    <span>Cadastre um objeto ou altere os filtros.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Objeto</th>
                            <th>Tipo</th>
                            <th>Cliente</th>
                            <th>ASN / Prefixo</th>
                            <th>Fonte</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($objects as $object)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link table-mono"
                                        href="{{ route('irr-objects.show', $object) }}"
                                    >
                                        {{ $object->object_key }}
                                    </a>

                                    @if ($object->description)
                                        <span class="table-secondary-text">
                                            {{ $object->description }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="irr-type-pill">
                                        {{ $object->displayType() }}
                                    </span>
                                </td>

                                <td>
                                    @if ($object->client)
                                        <a
                                            class="table-action-link"
                                            href="{{ route('clients.show', $object->client) }}"
                                        >
                                            {{ $object->client->displayName() }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td>
                                    @if ($object->autonomousSystem)
                                        <div class="table-mono">
                                            {{ $object->autonomousSystem->formattedAsn() }}
                                        </div>
                                    @endif

                                    @if ($object->prefix)
                                        <div class="table-secondary-text table-mono">
                                            {{ $object->prefix->prefix }}
                                        </div>
                                    @endif

                                    @if (! $object->autonomousSystem && ! $object->prefix)
                                        —
                                    @endif
                                </td>

                                <td>{{ $object->source }}</td>

                                <td>
                                    <span class="status-pill {{ $object->active ? 'is-active' : 'is-inactive' }}">
                                        {{ $object->active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{ route('irr-objects.show', $object) }}"
                                        >
                                            Abrir
                                        </a>

                                        @if (auth()->user()->isAdministrator())
                                            <a
                                                class="table-action-link"
                                                href="{{ route('irr-objects.edit', $object) }}"
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

            @if ($objects->hasPages())
                <div class="pagination-simple">
                    @if ($objects->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $objects->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $objects->currentPage() }}
                        de {{ $objects->lastPage() }}
                    </span>

                    @if ($objects->hasMorePages())
                        <a href="{{ $objects->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
