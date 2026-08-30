@extends('layouts.app')

@section('title', 'Usuários | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Administração
            </div>

            <h1>Usuários</h1>

            <p>
                Controle de acesso e perfis da plataforma.
            </p>
        </div>

        <a class="button button-primary" href="{{ route('users.create') }}">
            Novo usuário
        </a>
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        <form class="filter-bar" method="GET" action="{{ route('users.index') }}">
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Nome, e-mail ou IP..."
                >
            </div>

            <select class="filter-select" name="role" aria-label="Filtrar por perfil">
                <option value="">Todos os perfis</option>

                @foreach ($roles as $item)
                    <option value="{{ $item }}" @selected($role === $item)>
                        {{ match ($item) {
                            'admin' => 'Administrador',
                            'operator' => 'Operador',
                            default => 'Somente leitura',
                        } }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="status" aria-label="Filtrar por estado">
                <option value="">Todos os estados</option>
                <option value="active" @selected($status === 'active')>
                    Ativos
                </option>
                <option value="inactive" @selected($status === 'inactive')>
                    Bloqueados
                </option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if ($search !== '' || $role !== '' || $status !== '')
                <a class="button button-ghost" href="{{ route('users.index') }}">
                    Limpar
                </a>
            @endif
        </form>

        @if ($users->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="users" size="25"/>
                </div>

                <div>
                    <strong>Nenhum usuário encontrado</strong>
                    <span>
                        Cadastre um usuário ou altere os filtros de busca.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Perfil</th>
                            <th>Estado</th>
                            <th>Último acesso</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="table-identity">
                                        <x-user-avatar :user="$user" size="small"/>

                                        <div class="table-identity-text">
                                            <strong>{{ $user->name }}</strong>
                                            <span class="table-secondary-text">
                                                {{ $user->email }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="role-pill is-{{ $user->role }}">
                                        {{ $user->roleLabel() }}
                                    </span>
                                </td>

                                <td>
                                    <span class="status-pill {{ $user->active ? 'is-active' : 'is-inactive' }}">
                                        {{ $user->active ? 'Ativo' : 'Bloqueado' }}
                                    </span>
                                </td>

                                <td>
                                    @if ($user->last_login_at)
                                        {{ app(\App\Support\BusinessClock::class)
                                            ->toBusinessTimezone($user->last_login_at)
                                            ->format('d/m/Y H:i') }}

                                        @if ($user->last_login_ip)
                                            <span class="table-secondary-text table-mono">
                                                {{ $user->last_login_ip }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="table-secondary-text">Nunca acessou</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-icon-button"
                                            href="{{ route('users.edit', $user) }}"
                                            aria-label="Editar {{ $user->name }}"
                                            title="Editar"
                                        >
                                            <x-icon name="edit" size="15"/>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="pagination-simple">
                    @if ($users->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $users->currentPage() }}
                        de {{ $users->lastPage() }}
                    </span>

                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
