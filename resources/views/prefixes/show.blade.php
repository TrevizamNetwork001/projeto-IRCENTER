@extends('layouts.app')

@section('title', $prefix->prefix.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Prefixo IPv{{ $prefix->ip_version }}
            </div>

            <h1 class="table-mono">{{ $prefix->prefix }}</h1>

            <p>
                {{ $prefix->description ?: 'Recurso de numeração cadastrado no IRCENTER.' }}
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('prefixes.edit', $prefix) }}"
                >
                    Editar
                </a>

                <form
                    method="POST"
                    action="{{ route('prefixes.toggle-active', $prefix) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button class="button button-secondary" type="submit">
                        {{ $prefix->active ? 'Desativar' : 'Ativar' }}
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
                    <span class="panel-eyebrow">Recurso</span>
                    <h2>Informações principais</h2>
                </div>

                <span class="status-pill {{ $prefix->active ? 'is-active' : 'is-inactive' }}">
                    {{ $prefix->active ? 'Ativo' : 'Inativo' }}
                </span>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Prefixo</dt>
                    <dd class="table-mono">{{ $prefix->prefix }}</dd>
                </div>

                <div>
                    <dt>Versão</dt>
                    <dd>IPv{{ $prefix->ip_version }}</dd>
                </div>

                <div>
                    <dt>RIR</dt>
                    <dd>{{ $prefix->rir ?: '—' }}</dd>
                </div>

                <div>
                    <dt>País</dt>
                    <dd>{{ $prefix->country }}</dd>
                </div>

                <div>
                    <dt>Status de alocação</dt>
                    <dd>
                        {{ match ($prefix->allocation_status) {
                            'allocated' => 'Alocado',
                            'assigned' => 'Designado',
                            'reserved' => 'Reservado',
                            'legacy' => 'Legado',
                            'available' => 'Disponível',
                            default => $prefix->allocation_status,
                        } }}
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Vínculos</span>
                    <h2>Cliente e origem</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Cliente</dt>
                    <dd>
                        <a
                            class="inline-link"
                            href="{{ route('clients.show', $prefix->client) }}"
                        >
                            {{ $prefix->client->displayName() }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt>ASN</dt>
                    <dd>
                        @if ($prefix->autonomousSystem)
                            <a
                                class="inline-link table-mono"
                                href="{{ route(
                                    'autonomous-systems.show',
                                    $prefix->autonomousSystem
                                ) }}"
                            >
                                {{ $prefix->autonomousSystem->formattedAsn() }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Finalidade</dt>
                    <dd>{{ $prefix->purpose ?: '—' }}</dd>
                </div>
            </dl>
        </article>

        @php
            $rpkiValidation = $prefix->latestRpkiValidation;
            $rpkiStatus = $rpkiValidation?->status ?? 'unchecked';
        @endphp

        <article class="panel details-card details-card-wide">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Segurança de roteamento</span>
                    <h2>Validação RPKI</h2>
                </div>

                <span class="rpki-status-pill is-{{ $rpkiStatus }}">
                    @if ($rpkiValidation)
                        {{ $rpkiValidation->displayStatus() }}
                    @else
                        Não verificado
                    @endif
                </span>
            </header>

            <div class="rpki-prefix-overview">
                <div>
                    <span>Última verificação</span>
                    <strong>
                        {{ $rpkiValidation?->checked_at?->format('d/m/Y H:i') ?: 'Nunca' }}
                    </strong>
                </div>

                <div>
                    <span>ASN validado</span>
                    <strong class="table-mono">
                        {{ $rpkiValidation?->validated_asn
                            ? 'AS'.$rpkiValidation->validated_asn
                            : '—' }}
                    </strong>
                </div>

                <div>
                    <span>ROAs encontrados</span>
                    <strong>
                        {{ $rpkiValidation?->matching_roas_count ?? 0 }}
                    </strong>
                </div>

                <div>
                    <span>Motivo</span>
                    <strong>
                        {{ match ($rpkiValidation?->reason) {
                            'matching_roa' => 'ROA compatível',
                            'origin_asn_mismatch' => 'ASN divergente',
                            'max_length_exceeded' => 'Comprimento excedido',
                            'no_covering_roa' => 'Nenhum ROA cobrindo',
                            'missing_origin_asn' => 'ASN não vinculado',
                            'invalid_prefix' => 'Prefixo inválido',
                            null => 'Ainda não validado',
                            default => $rpkiValidation->reason,
                        } }}
                    </strong>
                </div>
            </div>

            <div class="form-actions rpki-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('rpki.history', $prefix) }}"
                >
                    Ver histórico
                </a>

                @if (auth()->user()->isAdministrator())
                    <form
                        method="POST"
                        action="{{ route(
                            'prefixes.rpki-validation.store',
                            $prefix
                        ) }}"
                    >
                        @csrf

                        <button class="button button-primary" type="submit">
                            Executar validação
                        </button>
                    </form>
                @endif
            </div>
        </article>

        <article class="panel details-card details-card-wide">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Contexto</span>
                    <h2>Descrição e observações</h2>
                </div>
            </header>

            <div class="notes-content">
                @if ($prefix->description)
                    {{ $prefix->description }}

                    @if ($prefix->notes)

{{ $prefix->notes }}
                    @endif
                @else
                    {{ $prefix->notes ?: 'Nenhuma descrição ou observação cadastrada.' }}
                @endif
            </div>
        </article>
    </section>
@endsection
