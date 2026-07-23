@extends('layouts.app')

@section('title', $irrObject->object_key.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                {{ $irrObject->displayType() }}
            </div>

            <h1 class="table-mono">{{ $irrObject->object_key }}</h1>

            <p>
                {{ $irrObject->description ?: 'Objeto IRR cadastrado no IRCENTER.' }}
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('irr-objects.edit', $irrObject) }}"
                >
                    Editar
                </a>

                <form
                    method="POST"
                    action="{{ route('irr-objects.toggle-active', $irrObject) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button class="button button-secondary" type="submit">
                        {{ $irrObject->active ? 'Desativar' : 'Ativar' }}
                    </button>
                </form>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <section class="details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Identidade</span>
                    <h2>Dados do objeto</h2>
                </div>

                <span class="status-pill {{ $irrObject->active ? 'is-active' : 'is-inactive' }}">
                    {{ $irrObject->active ? 'Ativo' : 'Inativo' }}
                </span>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Tipo</dt>
                    <dd>{{ $irrObject->displayType() }}</dd>
                </div>

                <div>
                    <dt>Chave</dt>
                    <dd class="table-mono">{{ $irrObject->object_key }}</dd>
                </div>

                <div>
                    <dt>Fonte</dt>
                    <dd>{{ $irrObject->source }}</dd>
                </div>

                <div>
                    <dt>Maintainer</dt>
                    <dd class="table-mono">{{ $irrObject->maintainer ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Status</dt>
                    <dd>{{ ucfirst($irrObject->status) }}</dd>
                </div>

                <div>
                    <dt>Última sincronização</dt>
                    <dd>
                        {{ $irrObject->last_synced_at?->format('d/m/Y H:i') ?: 'Nunca' }}
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Relacionamentos</span>
                    <h2>Inventário vinculado</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Cliente</dt>
                    <dd>
                        @if ($irrObject->client)
                            <a
                                class="inline-link"
                                href="{{ route('clients.show', $irrObject->client) }}"
                            >
                                {{ $irrObject->client->displayName() }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>ASN</dt>
                    <dd>
                        @if ($irrObject->autonomousSystem)
                            <a
                                class="inline-link table-mono"
                                href="{{ route(
                                    'autonomous-systems.show',
                                    $irrObject->autonomousSystem
                                ) }}"
                            >
                                {{ $irrObject->autonomousSystem->formattedAsn() }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Prefixo</dt>
                    <dd>
                        @if ($irrObject->prefix)
                            <a
                                class="inline-link table-mono"
                                href="{{ route('prefixes.show', $irrObject->prefix) }}"
                            >
                                {{ $irrObject->prefix->prefix }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card details-card-wide">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Conteúdo</span>
                    <h2>Objeto bruto</h2>
                </div>
            </header>

            <pre class="irr-raw-object">{{ $irrObject->raw_text ?: 'Nenhum conteúdo bruto cadastrado.' }}</pre>
        </article>
    </section>
@endsection
