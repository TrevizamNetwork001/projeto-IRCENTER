@extends('layouts.app')

@section('title', 'Maintainers IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>Maintainers</h1>
            <p>Mntners com senha cadastrada para publicação automática de objetos no TC.</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('irr-maintainers.create') }}">
                    Novo maintainer
                </a>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <section class="panel">
        @if ($maintainers->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="registry" size="25"/>
                </div>

                <div>
                    <strong>Nenhum maintainer cadastrado</strong>
                    <span>Cadastre a senha de um mntner já existente no TC.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mntner</th>
                            <th>ASN</th>
                            <th>Cliente</th>
                            <th>Objetos</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($maintainers as $item)
                            <tr>
                                <td>
                                    <a class="table-primary-link table-mono" href="{{ route('irr-maintainers.show', $item) }}">
                                        {{ $item->mntner }}
                                    </a>
                                </td>

                                <td class="table-mono">{{ $item->formattedAsn() }}</td>

                                <td>
                                    {{ $item->client?->displayName() ?? '—' }}
                                </td>

                                <td>
                                    {{ $item->routes_count }} route(s), {{ $item->as_sets_count }} as-set(s)
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a class="table-action-link" href="{{ route('irr-maintainers.show', $item) }}">Abrir</a>

                                        @if (auth()->user()->isAdministrator())
                                            <a class="table-action-link" href="{{ route('irr-maintainers.edit', $item) }}">Editar</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($maintainers->hasPages())
                <div class="pagination-simple">
                    @if ($maintainers->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $maintainers->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>Página {{ $maintainers->currentPage() }} de {{ $maintainers->lastPage() }}</span>

                    @if ($maintainers->hasMorePages())
                        <a href="{{ $maintainers->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
