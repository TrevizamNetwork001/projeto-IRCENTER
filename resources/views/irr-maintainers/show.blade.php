@extends('layouts.app')

@section('title', $maintainer->mntner.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1 class="table-mono">{{ $maintainer->mntner }}</h1>
            <p>{{ $maintainer->formattedAsn() }} — {{ $maintainer->client?->displayName() ?? 'Sem cliente vinculado' }}</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a class="button button-secondary" href="{{ route('irr-maintainers.edit', $maintainer) }}">Editar</a>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <section class="panel">
        <dl class="detail-grid">
            <div>
                <dt>admin-c</dt>
                <dd class="table-mono">{{ $maintainer->admin_c }}</dd>
            </div>

            <div>
                <dt>tech-c</dt>
                <dd class="table-mono">{{ $maintainer->tech_c }}</dd>
            </div>

            <div>
                <dt>E-mail de notificação</dt>
                <dd>{{ $maintainer->notify_email ?? '—' }}</dd>
            </div>

            <div>
                <dt>Descrição</dt>
                <dd>{{ $maintainer->descr ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    <section class="panel">
        <h2>Objetos route/route6 ({{ $maintainer->routes->count() }})</h2>

        @if ($maintainer->routes->isEmpty())
            <p class="table-secondary-text">Nenhum objeto route cadastrado para este maintainer.</p>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Prefixo</th>
                            <th>Origem</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($maintainer->routes as $route)
                            <tr>
                                <td class="table-mono">{{ $route->prefix }}</td>
                                <td class="table-mono">AS{{ $route->origin_asn }}</td>
                                <td>
                                    <span class="status-pill {{ $route->status === 'published' ? 'is-active' : 'is-inactive' }}">
                                        {{ $route->status }}
                                    </span>
                                </td>
                                <td>
                                    <a class="table-action-link" href="{{ route('irr-routes.show', $route) }}">Abrir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <h2>AS-sets ({{ $maintainer->asSets->count() }})</h2>

        @if ($maintainer->asSets->isEmpty())
            <p class="table-secondary-text">Nenhum AS-set cadastrado para este maintainer.</p>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($maintainer->asSets as $asSet)
                            <tr>
                                <td class="table-mono">{{ $asSet->name }}</td>
                                <td>
                                    <span class="status-pill {{ $asSet->status === 'published' ? 'is-active' : 'is-inactive' }}">
                                        {{ $asSet->status }}
                                    </span>
                                </td>
                                <td>
                                    <a class="table-action-link" href="{{ route('irr-as-sets.show', $asSet) }}">Abrir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
