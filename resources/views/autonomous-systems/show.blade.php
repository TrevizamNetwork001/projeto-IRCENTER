@extends('layouts.app')

@section('title', $autonomousSystem->formattedAsn().' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Sistema autônomo
            </div>

            <h1>{{ $autonomousSystem->formattedAsn() }}</h1>
            <p>{{ $autonomousSystem->name }}</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('autonomous-systems.edit', $autonomousSystem) }}"
                >
                    Editar
                </a>

                <form
                    method="POST"
                    action="{{ route('autonomous-systems.toggle-active', $autonomousSystem) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button class="button button-secondary" type="submit">
                        {{ $autonomousSystem->active ? 'Desativar' : 'Ativar' }}
                    </button>
                </form>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Cadastro</span>
                    <h2>Informações principais</h2>
                </div>

                <span class="status-pill {{ $autonomousSystem->active ? 'is-active' : 'is-inactive' }}">
                    {{ $autonomousSystem->active ? 'Ativo' : 'Inativo' }}
                </span>
            </header>

            <dl class="details-list">
                <div>
                    <dt>ASN</dt>
                    <dd class="table-mono">{{ $autonomousSystem->formattedAsn() }}</dd>
                </div>

                <div>
                    <dt>Nome</dt>
                    <dd>{{ $autonomousSystem->name }}</dd>
                </div>

                <div>
                    <dt>Cliente</dt>
                    <dd>
                        <a
                            class="inline-link"
                            href="{{ route('clients.show', $autonomousSystem->client) }}"
                        >
                            {{ $autonomousSystem->client->displayName() }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt>RIR</dt>
                    <dd>{{ $autonomousSystem->rir ?: '—' }}</dd>
                </div>

                <div>
                    <dt>País</dt>
                    <dd>{{ $autonomousSystem->country }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">NOC</span>
                    <h2>Contato operacional</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Contato</dt>
                    <dd>{{ $autonomousSystem->noc_contact ?: '—' }}</dd>
                </div>

                <div>
                    <dt>E-mail</dt>
                    <dd>{{ $autonomousSystem->noc_email ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Telefone</dt>
                    <dd>{{ $autonomousSystem->noc_phone ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Site</dt>
                    <dd>
                        @if ($autonomousSystem->website)
                            <a
                                class="inline-link"
                                href="{{ $autonomousSystem->website }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ $autonomousSystem->website }}
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
                    <span class="panel-eyebrow">Contexto</span>
                    <h2>Descrição e observações</h2>
                </div>
            </header>

            <div class="notes-content">
                @if ($autonomousSystem->description)
                    {{ $autonomousSystem->description }}

                    @if ($autonomousSystem->notes)

{{ $autonomousSystem->notes }}
                    @endif
                @else
                    {{ $autonomousSystem->notes ?: 'Nenhuma descrição ou observação cadastrada.' }}
                @endif
            </div>
        </article>
    </section>
@endsection
