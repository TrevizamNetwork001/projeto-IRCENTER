@extends('layouts.app')

@section('title', 'Auditoria | IRCENTER')

@section('content')
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">Governança</span>
            <h1>Auditoria</h1>
            <p>
                Histórico de alterações executadas na plataforma.
            </p>
        </div>
    </div>

    <section class="panel">
        <form class="filter-bar" method="GET">
            <input
                class="form-control"
                name="search"
                type="search"
                value="{{ $search }}"
                placeholder="Recurso, usuário, ação ou IP..."
            >

            <select class="form-control" name="action">
                <option value="">Todas as ações</option>

                @foreach ($actions as $item)
                    <option
                        value="{{ $item }}"
                        @selected($action === $item)
                    >
                        {{ $item }}
                    </option>
                @endforeach
            </select>

            <select class="form-control" name="resource_type">
                <option value="">Todos os recursos</option>

                @foreach ($resourceTypes as $item)
                    <option
                        value="{{ $item }}"
                        @selected($resourceType === $item)
                    >
                        {{ $item }}
                    </option>
                @endforeach
            </select>

            <select class="form-control" name="user">
                <option value="">Todos os usuários</option>

                @foreach ($users as $user)
                    <option
                        value="{{ $user->id }}"
                        @selected($userId === $user->id)
                    >
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>

            <button class="button button-primary" type="submit">
                Filtrar
            </button>
        </form>
    </section>

    <section class="panel">
        @if ($logs->isEmpty())
            <div class="empty-state">
                <strong>Nenhum registro encontrado</strong>
                <span>
                    As alterações futuras aparecerão aqui.
                </span>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Usuário</th>
                            <th>Ação</th>
                            <th>Recurso</th>
                            <th>Identificação</th>
                            <th>IP</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>
                                    {{ $log->created_at?->format(
                                        'd/m/Y H:i:s'
                                    ) }}
                                </td>

                                <td>
                                    {{ $log->user?->name ?? 'Sistema' }}
                                </td>

                                <td>
                                    <span class="status-badge">
                                        {{ $log->action }}
                                    </span>
                                </td>

                                <td>{{ $log->resource_type }}</td>

                                <td>
                                    <strong>
                                        {{ $log->resource_label
                                            ?? '#'.$log->resource_id }}
                                    </strong>
                                </td>

                                <td class="table-mono">
                                    {{ $log->ip_address ?? '—' }}
                                </td>

                                <td class="table-actions">
                                    <a
                                        class="button button-secondary"
                                        href="{{ route(
                                            'audit.show',
                                            $log
                                        ) }}"
                                    >
                                        Detalhes
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        @endif
    </section>
@endsection
