@extends('layouts.app')

@section('title', 'AS-sets — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>AS-sets</h1>
            <p>AS-sets publicados diretamente na base do TC via API.</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('irr-as-sets.create') }}">
                    Novo AS-set
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
        @if ($asSets->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="registry" size="25"/>
                </div>

                <div>
                    <strong>Nenhum AS-set cadastrado</strong>
                    <span>Cadastre um AS-set para publicar no TC.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Maintainer</th>
                            <th>Membros</th>
                            <th>Status</th>
                            <th>Última publicação</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($asSets as $item)
                            <tr>
                                <td>
                                    <a class="table-primary-link table-mono" href="{{ route('irr-as-sets.show', $item) }}">
                                        {{ $item->name }}
                                    </a>
                                </td>

                                <td class="table-mono">{{ $item->maintainer->mntner }}</td>

                                <td>{{ count($item->members ?? []) }}</td>

                                <td>
                                    <span class="status-pill {{ $item->status === 'published' ? 'is-active' : 'is-inactive' }}">
                                        {{ $item->status }}
                                    </span>
                                </td>

                                <td>{{ $item->last_published_at?->format('d/m/Y H:i') ?? '—' }}</td>

                                <td>
                                    <div class="table-actions">
                                        <a class="table-action-link" href="{{ route('irr-as-sets.show', $item) }}">Abrir</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($asSets->hasPages())
                <div class="pagination-simple">
                    @if ($asSets->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $asSets->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>Página {{ $asSets->currentPage() }} de {{ $asSets->lastPage() }}</span>

                    @if ($asSets->hasMorePages())
                        <a href="{{ $asSets->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
