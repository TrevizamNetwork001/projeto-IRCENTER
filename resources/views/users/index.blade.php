@extends('layouts.app')

@section('title', 'Usuários | IRCENTER')

@section('content')
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">Administração</span>
            <h1>Usuários</h1>
            <p>
                Controle de acesso e perfis da plataforma.
            </p>
        </div>

        <a class="button button-primary" href="{{ route('users.create') }}">
            Novo usuário
        </a>
    </div>

    <section class="panel">
        <form class="filter-bar" method="GET">
            <input
                class="form-control"
                name="search"
                type="search"
                value="{{ $search }}"
                placeholder="Nome, e-mail ou IP..."
            >

            <select class="form-control" name="role">
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

            <select class="form-control" name="status">
                <option value="">Todos os estados</option>
                <option value="active" @selected($status === 'active')>
                    Ativos
                </option>
                <option value="inactive" @selected($status === 'inactive')>
                    Bloqueados
                </option>
            </select>

            <button class="button button-primary" type="submit">
                Filtrar
            </button>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th>Último acesso</th>
                        <th>Último IP</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong>
                                <div class="table-secondary">
                                    {{ $user->email }}
                                </div>
                            </td>

                            <td>{{ $user->roleLabel() }}</td>

                            <td>
                                <span class="status-badge">
                                    {{ $user->active
                                        ? 'Ativo'
                                        : 'Bloqueado' }}
                                </span>
                            </td>

                            <td>
                                {{ $user->last_login_at
                                    ?->format('d/m/Y H:i') ?? 'Nunca' }}
                            </td>

                            <td class="table-mono">
                                {{ $user->last_login_ip ?? '—' }}
                            </td>

                            <td class="table-actions">
                                <a
                                    class="button button-secondary"
                                    href="{{ route('users.edit', $user) }}"
                                >
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>Nenhum usuário encontrado</strong>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </section>
@endsection
