@extends('layouts.app')

@section('title', 'Objetos route — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>Objetos route</h1>
            <p>Objetos route/route6 publicados diretamente na base do TC via API.</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('irr-routes.create') }}">
                    Novo objeto route
                </a>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <section class="panel">
        @if ($routes->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="registry" size="25"/>
                </div>

                <div>
                    <strong>Nenhum objeto route cadastrado</strong>
                    <span>Cadastre um prefixo para publicar no TC.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prefixo</th>
                            <th>Origem</th>
                            <th>Maintainer</th>
                            <th>Status</th>
                            <th>Última publicação</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($routes as $item)
                            <tr>
                                <td>
                                    <a class="table-primary-link table-mono" href="{{ route('irr-routes.show', $item) }}">
                                        {{ $item->prefix }}
                                    </a>
                                </td>

                                <td class="table-mono">AS{{ $item->origin_asn }}</td>

                                <td class="table-mono">{{ $item->maintainer->mntner }}</td>

                                <td>
                                    <span class="status-pill {{ $item->status === 'published' ? 'is-active' : 'is-inactive' }}">
                                        {{ $item->status }}
                                    </span>
                                </td>

                                <td>{{ $item->last_published_at?->format('d/m/Y H:i') ?? '—' }}</td>

                                <td>
                                    <div class="table-actions">
                                        <a class="table-action-link" href="{{ route('irr-routes.show', $item) }}">Abrir</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($routes->hasPages())
                <div class="pagination-simple">
                    @if ($routes->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $routes->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>Página {{ $routes->currentPage() }} de {{ $routes->lastPage() }}</span>

                    @if ($routes->hasMorePages())
                        <a href="{{ $routes->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
