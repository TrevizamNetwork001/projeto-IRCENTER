@extends('layouts.app')

@section('title', $client->displayName().' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Cliente
            </div>

            <h1>{{ $client->displayName() }}</h1>

            <p>{{ $client->legal_name }}</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('clients.edit', $client) }}"
                >
                    Editar
                </a>

                <form
                    method="POST"
                    action="{{ route('clients.toggle-active', $client) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button class="button button-secondary" type="submit">
                        {{ $client->active ? 'Desativar' : 'Ativar' }}
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

                <span class="status-pill {{ $client->active ? 'is-active' : 'is-inactive' }}">
                    {{ $client->active ? 'Ativo' : 'Inativo' }}
                </span>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Razão social</dt>
                    <dd>{{ $client->legal_name }}</dd>
                </div>

                <div>
                    <dt>Nome fantasia</dt>
                    <dd>{{ $client->trade_name ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Documento</dt>
                    <dd class="table-mono">{{ $client->document ?: '—' }}</dd>
                </div>

                <div>
                    <dt>País</dt>
                    <dd>{{ $client->country }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Contato</span>
                    <h2>Localização e canais</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>E-mail</dt>
                    <dd>{{ $client->email ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Telefone</dt>
                    <dd>{{ $client->phone ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Site</dt>
                    <dd>
                        @if ($client->website)
                            <a
                                class="inline-link"
                                href="{{ $client->website }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ $client->website }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Localidade</dt>
                    <dd>
                        {{ collect([$client->city, $client->state])
                            ->filter()
                            ->implode(' / ') ?: '—' }}
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card details-card-wide">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Contexto</span>
                    <h2>Observações</h2>
                </div>
            </header>

            <div class="notes-content">
                {{ $client->notes ?: 'Nenhuma observação cadastrada.' }}
            </div>
        </article>
    </section>
@endsection
