@extends('layouts.app')

@section('title', $incident->reference.' — IRCENTER')

@section('content')
    <section class="page-heading incident-show-heading">
        <div>
            <div class="page-eyebrow">
                <span
                    class="incident-severity-dot
                        is-{{ $incident->severity }}"
                ></span>

                {{ $incident->reference }}
                · {{ $incident->typeLabel() }}
            </div>

            <h1>{{ $incident->title }}</h1>

            <p>{{ $incident->summary }}</p>
        </div>

        @if (auth()->user()->canOperate())
            <a
                class="button button-secondary"
                href="{{ route(
                    'routing-incidents.edit',
                    $incident
                ) }}"
            >
                Editar incidente
            </a>
        @endif
    </section>

    @if (session('success'))
        <div
            class="alert-success"
            data-auto-dismiss="5000"
        >
            {{ session('success') }}
        </div>
    @endif

    <section class="incident-overview-grid">
        <article class="panel incident-summary-card">
            <span>Severidade</span>

            <strong
                class="incident-severity-text
                    is-{{ $incident->severity }}"
            >
                {{ $incident->severityLabel() }}
            </strong>
        </article>

        <article class="panel incident-summary-card">
            <span>Status</span>

            <strong>{{ $incident->statusLabel() }}</strong>
        </article>

        <article class="panel incident-summary-card">
            <span>Detectado</span>

            <strong>
                {{ $incident->detected_at?->format('d/m/Y H:i') }}
            </strong>
        </article>

        <article class="panel incident-summary-card">
            <span>Responsável</span>

            <strong>
                {{ $incident->assignee?->name ?? 'Não atribuído' }}
            </strong>
        </article>
    </section>

    <section class="details-grid incident-details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Vínculos</span>
                    <h2>Recursos relacionados</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Cliente</dt>
                    <dd>
                        @if ($incident->client)
                            <a
                                class="inline-link"
                                href="{{ route(
                                    'clients.show',
                                    $incident->client
                                ) }}"
                            >
                                {{ $incident->client->displayName() }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>ASN</dt>
                    <dd>
                        @if ($incident->autonomousSystem)
                            <a
                                class="inline-link table-mono"
                                href="{{ route(
                                    'autonomous-systems.show',
                                    $incident->autonomousSystem
                                ) }}"
                            >
                                {{ $incident->autonomousSystem
                                    ->formattedAsn() }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Prefixo</dt>
                    <dd>
                        @if ($incident->prefix)
                            <a
                                class="inline-link table-mono"
                                href="{{ route(
                                    'prefixes.show',
                                    $incident->prefix
                                ) }}"
                            >
                                {{ $incident->prefix->prefix }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Origem</dt>
                    <dd>{{ $incident->source ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Referência externa</dt>
                    <dd>
                        {{ $incident->external_reference ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt>Registrado por</dt>
                    <dd>{{ $incident->reporter?->name ?? 'Sistema' }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Cronologia</span>
                    <h2>Marcos do incidente</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Detectado</dt>
                    <dd>
                        {{ $incident->detected_at
                            ?->format('d/m/Y H:i') }}
                    </dd>
                </div>

                <div>
                    <dt>Reconhecido</dt>
                    <dd>
                        {{ $incident->acknowledged_at
                            ?->format('d/m/Y H:i') ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt>Resolvido</dt>
                    <dd>
                        {{ $incident->resolved_at
                            ?->format('d/m/Y H:i') ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt>Encerrado</dt>
                    <dd>
                        {{ $incident->closed_at
                            ?->format('d/m/Y H:i') ?? '—' }}
                    </dd>
                </div>
            </dl>
        </article>

        @foreach ([
            'impact' => ['Impacto', 'Impacto observado'],
            'evidence' => ['Análise técnica', 'Evidências'],
            'mitigation' => ['Resposta', 'Mitigação'],
            'root_cause' => ['Conclusão', 'Causa raiz'],
        ] as $field => [$eyebrow, $title])
            <article class="panel details-card details-card-wide">
                <header class="panel-header">
                    <div>
                        <span class="panel-eyebrow">{{ $eyebrow }}</span>
                        <h2>{{ $title }}</h2>
                    </div>
                </header>

                <div class="notes-content">
                    {{ $incident->{$field}
                        ?: 'Nenhuma informação registrada.' }}
                </div>
            </article>
        @endforeach
    </section>

    <section class="incident-timeline-layout">
        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Histórico</span>
                    <h2>Linha do tempo</h2>
                </div>

                <span class="panel-status">
                    {{ $incident->updates->count() }}
                </span>
            </header>

            <div class="incident-timeline">
                @forelse ($incident->updates->sortByDesc('created_at') as $update)
                    <article class="incident-timeline-item">
                        <span
                            class="incident-timeline-marker
                                is-{{ $update->kind }}"
                        ></span>

                        <div>
                            <header>
                                <strong>
                                    {{ match ($update->kind) {
                                        'created' => 'Incidente registrado',
                                        'status_change' => 'Status alterado',
                                        default => 'Atualização operacional',
                                    } }}
                                </strong>

                                <time datetime="{{ $update->created_at }}">
                                    {{ $update->created_at
                                        ?->format('d/m/Y H:i') }}
                                </time>
                            </header>

                            <p>{{ $update->message }}</p>

                            <span>
                                {{ $update->user?->name ?? 'Sistema' }}
                            </span>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <x-icon name="activity" size="22"/>
                        </div>

                        <div>
                            <strong>Nenhuma atualização</strong>
                            <span>
                                A linha do tempo ainda está vazia.
                            </span>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>

        @if (auth()->user()->canOperate())
            <article class="panel incident-update-panel">
                <header class="panel-header">
                    <div>
                        <span class="panel-eyebrow">Operação</span>
                        <h2>Adicionar atualização</h2>
                    </div>
                </header>

                <form
                    method="POST"
                    action="{{ route(
                        'routing-incidents.updates.store',
                        $incident
                    ) }}"
                >
                    @csrf

                    <div class="field-group">
                        <label for="message">
                            Relato operacional <span>*</span>
                        </label>

                        <textarea
                            id="message"
                            class="form-control incident-update-textarea"
                            name="message"
                            maxlength="10000"
                            required
                        >{{ old('message') }}</textarea>

                        @error('message')
                            <div class="field-error">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <button
                            class="button button-primary"
                            type="submit"
                        >
                            Adicionar à linha do tempo
                        </button>
                    </div>
                </form>
            </article>
        @endif
    </section>
@endsection
