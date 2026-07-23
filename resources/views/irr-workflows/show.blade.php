@extends('layouts.app')

@section('title', $workflow->name.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Assistente IRR
            </div>

            <h1>{{ $workflow->name }}</h1>

            <p>
                {{ $workflow->client->displayName() }}
                ·
                <span class="table-mono">
                    {{ $workflow->autonomousSystem->formattedAsn() }}
                </span>
                · Fonte {{ $workflow->irr_source }}
            </p>
        </div>

        <div class="page-heading-status">
            <span>Progresso</span>
            <strong>{{ $workflow->current_step }} de 6</strong>
        </div>
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="panel workflow-prefix-policy">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">
                    Política de anúncios
                </span>

                <h2>Prefixos do workflow</h2>

                <p>
                    Defina como cada bloco aparecerá no route-set e se
                    deverá gerar um objeto route ou route6.
                </p>
            </div>

            <span class="workflow-status is-ready">
                {{ strtoupper($workflow->profile_key) }}
            </span>
        </header>

        @if ($workflow->prefixes->isEmpty())
            <div class="empty-state">
                <strong>Nenhum prefixo vinculado ao ASN</strong>

                <span>
                    Cadastre os prefixos antes de preparar o route-set.
                </span>
            </div>
        @else
            <div class="workflow-prefix-list">
                @foreach (
                    $workflow->prefixes
                        ->sortBy([
                            ['ip_version', 'asc'],
                            ['prefix', 'asc'],
                        ])
                    as $workflowPrefix
                )
                    <form
                        class="workflow-prefix-row"
                        method="POST"
                        action="{{ route(
                            'irr-workflows.prefixes.update',
                            [$workflow, $workflowPrefix]
                        ) }}"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="workflow-prefix-identity">
                            <span class="workflow-prefix-family">
                                IPv{{ $workflowPrefix->ip_version }}
                            </span>

                            <strong class="table-mono">
                                {{ $workflowPrefix->prefix }}
                            </strong>

                            <span class="table-mono workflow-prefix-preview">
                                {{ $workflowPrefix->routeSetMember() }}
                            </span>
                        </div>

                        <div class="field-group">
                            <label for="mode-{{ $workflowPrefix->id }}">
                                Route-set
                            </label>

                            <select
                                id="mode-{{ $workflowPrefix->id }}"
                                class="form-control prefix-mode-field"
                                name="route_set_mode"
                                data-prefix-id="{{ $workflowPrefix->id }}"
                            >
                                <option
                                    value="exact"
                                    @selected(
                                        $workflowPrefix->route_set_mode
                                        === 'exact'
                                    )
                                >
                                    Somente prefixo exato
                                </option>

                                <option
                                    value="more_specifics"
                                    @selected(
                                        $workflowPrefix->route_set_mode
                                        === 'more_specifics'
                                    )
                                >
                                    Permitir mais específicos
                                </option>
                            </select>
                        </div>

                        <div
                            class="field-group prefix-maximum-group"
                            data-prefix-maximum="{{ $workflowPrefix->id }}"
                        >
                            <label for="maximum-{{ $workflowPrefix->id }}">
                                Limite máximo
                            </label>

                            <input
                                id="maximum-{{ $workflowPrefix->id }}"
                                class="form-control"
                                name="maximum_length"
                                type="number"
                                min="{{ $workflowPrefix->prefixLength() + 1 }}"
                                max="{{ $workflowPrefix->ip_version === 6
                                    ? 128
                                    : 32 }}"
                                value="{{ $workflowPrefix->maximum_length }}"
                            >
                        </div>

                        <label class="workflow-prefix-checkbox">
                            <input
                                type="hidden"
                                name="generate_route_object"
                                value="0"
                            >

                            <input
                                name="generate_route_object"
                                type="checkbox"
                                value="1"
                                @checked(
                                    $workflowPrefix->generate_route_object
                                )
                            >

                            <span>
                                Gerar
                                {{ $workflowPrefix->ip_version === 6
                                    ? 'route6'
                                    : 'route' }}
                            </span>
                        </label>

                        <button
                            class="button button-secondary"
                            type="submit"
                        >
                            Salvar política
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>

    <section class="workflow-progress">
        @foreach ($workflow->steps as $step)
            <div
                class="workflow-progress-item is-{{ $step->status }}"
                title="{{ $step->title }}"
            >
                <span>{{ $step->step_number }}</span>
                <strong>{{ $step->title }}</strong>
            </div>
        @endforeach
    </section>

    <section class="workflow-layout">
        <aside class="panel workflow-sidebar">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Etapas</span>
                    <h2>Roteiro da implantação</h2>
                </div>
            </header>

            <div class="workflow-step-list">
                @foreach ($workflow->steps as $step)
                    <a
                        href="#step-{{ $step->step_number }}"
                        class="workflow-step-link is-{{ $step->status }}"
                    >
                        <span class="workflow-step-number">
                            {{ $step->step_number }}
                        </span>

                        <div>
                            <strong>{{ $step->title }}</strong>

                            <span>
                                {{ match ($step->status) {
                                    'locked' => 'Bloqueada',
                                    'ready' => 'Pronta para enviar',
                                    'sent' => 'Enviada',
                                    'waiting_confirmation' => 'Aguardando confirmação',
                                    'completed' => 'Concluída',
                                    'error' => 'Requer correção',
                                    default => $step->status,
                                } }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </aside>

        <div class="workflow-main">
            @foreach ($workflow->steps as $step)
                @if ($step->status === 'completed')
                    <details
                        id="step-{{ $step->step_number }}"
                        class="panel workflow-step-card workflow-step-collapsed is-completed"
                    >
                        <summary class="workflow-collapsed-summary">
                            <div>
                                <span class="panel-eyebrow">
                                    Etapa {{ $step->step_number }}
                                </span>

                                <strong>{{ $step->title }}</strong>
                            </div>

                            <div class="workflow-collapsed-meta">
                                <span class="workflow-status is-completed">
                                    Concluída
                                </span>

                                <span>
                                    {{ $step->confirmed_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>
                        </summary>

                        <div class="workflow-collapsed-content">
                @else
                    <article
                        id="step-{{ $step->step_number }}"
                        class="panel workflow-step-card is-{{ $step->status }}"
                    >
                @endif
                    <header class="panel-header">
                        <div>
                            <span class="panel-eyebrow">
                                Etapa {{ $step->step_number }}
                            </span>

                            <h2>{{ $step->title }}</h2>
                        </div>

                        <span class="workflow-status is-{{ $step->status }}">
                            {{ match ($step->status) {
                                'locked' => 'Bloqueada',
                                'ready' => 'Pronta',
                                'sent' => 'Enviada',
                                'waiting_confirmation' => 'Aguardando confirmação',
                                'completed' => 'Concluída',
                                'error' => 'Erro',
                                default => $step->status,
                            } }}
                        </span>
                    </header>

                    <p class="workflow-instructions">
                        {{ $step->instructions }}
                    </p>

                    @if ($step->status === 'locked')
                        <div class="workflow-locked">
                            <x-icon name="lock" size="20"/>

                            <span>
                                Conclua e confirme a etapa anterior para liberar
                                este conteúdo.
                            </span>
                        </div>
                    @else
                        <div class="workflow-content-grid">
                            <section>
                                <div class="workflow-content-header">
                                    <strong>E-mail preparado</strong>

                                    <button
                                        class="button button-ghost copy-button"
                                        type="button"
                                        data-copy-target="email-{{ $step->id }}"
                                    >
                                        Copiar e-mail
                                    </button>
                                </div>

                                <dl class="workflow-email-meta">
                                    <div>
                                        <dt>Para</dt>
                                        <dd>
                                            {{ $step->email_to ?: 'Definir antes do envio' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Assunto</dt>
                                        <dd>{{ $step->email_subject ?: '—' }}</dd>
                                    </div>
                                </dl>

                                <pre
                                    id="email-{{ $step->id }}"
                                    class="workflow-code"
                                >{{ $step->email_body ?: 'Nenhum e-mail preparado.' }}</pre>
                            </section>

                            <section>
                                <div class="workflow-content-header">
                                    <strong>Conteúdo RPSL</strong>

                                    <button
                                        class="button button-ghost copy-button"
                                        type="button"
                                        data-copy-target="rpsl-{{ $step->id }}"
                                    >
                                        Copiar RPSL
                                    </button>
                                </div>

                                <pre
                                    id="rpsl-{{ $step->id }}"
                                    class="workflow-code"
                                >{{ $step->rpsl_content ?: 'Nenhum conteúdo preparado.' }}</pre>
                            </section>
                        </div>

                        <div class="workflow-timeline">
                            <div>
                                <span>Preparada</span>
                                <strong>
                                    {{ $step->prepared_at?->format('d/m/Y H:i') ?: '—' }}
                                </strong>
                            </div>

                            <div>
                                <span>Enviada</span>
                                <strong>
                                    {{ $step->sent_at?->format('d/m/Y H:i') ?: '—' }}
                                </strong>
                            </div>

                            <div>
                                <span>Confirmada</span>
                                <strong>
                                    {{ $step->confirmed_at?->format('d/m/Y H:i') ?: '—' }}
                                </strong>
                            </div>
                        </div>

                        @if ($step->status === 'ready')
                            <form
                                class="workflow-action-form"
                                method="POST"
                                action="{{ route(
                                    'irr-workflows.steps.sent',
                                    [$workflow, $step]
                                ) }}"
                            >
                                @csrf

                                <div class="field-group">
                                    <label for="sent-notes-{{ $step->id }}">
                                        Observação do envio
                                    </label>

                                    <textarea
                                        id="sent-notes-{{ $step->id }}"
                                        class="form-control"
                                        name="operator_notes"
                                        rows="2"
                                        maxlength="5000"
                                        placeholder="Ex.: enviado pelo operador às 14h."
                                    ></textarea>
                                </div>

                                <button class="button button-primary" type="submit">
                                    {{ $step->step_key === 'review'
                                        ? 'Revisão realizada'
                                        : 'Marcar como enviado' }}
                                </button>
                            </form>
                        @elseif ($step->status === 'waiting_confirmation')
                            <form
                                class="workflow-action-form"
                                method="POST"
                                action="{{ route(
                                    'irr-workflows.steps.confirm',
                                    [$workflow, $step]
                                ) }}"
                            >
                                @csrf

                                <div class="field-group">
                                    <label for="confirm-notes-{{ $step->id }}">
                                        Informação da confirmação
                                    </label>

                                    <textarea
                                        id="confirm-notes-{{ $step->id }}"
                                        class="form-control"
                                        name="operator_notes"
                                        rows="2"
                                        maxlength="5000"
                                        placeholder="Ex.: objeto confirmado pela base IRR."
                                    ></textarea>
                                </div>

                                <button class="button button-primary" type="submit">
                                    {{ $step->step_key === 'review'
                                        ? 'Concluir implantação'
                                        : 'Confirmar aprovação' }}
                                </button>
                            </form>
                        @elseif ($step->status === 'completed')
                            <div class="workflow-completed-message">
                                <x-icon name="check" size="20"/>

                                <span>
                                    Etapa concluída. O histórico permanece
                                    disponível para consulta.
                                </span>
                            </div>
                        @endif

                        @if ($step->operator_notes)
                            <div class="workflow-operator-note">
                                <strong>Observação registrada</strong>
                                <p>{{ $step->operator_notes }}</p>
                            </div>
                        @endif
                    @endif
                @if ($step->status === 'completed')
                        </div>
                    </details>
                @else
                    </article>
                @endif
            @endforeach
        </div>
    </section>

    <script>
        (() => {
            const refreshPrefixMode = (field) => {
                const group = document.querySelector(
                    `[data-prefix-maximum="${field.dataset.prefixId}"]`
                );

                const input = group?.querySelector('input');

                if (! group || ! input) {
                    return;
                }

                const enabled = field.value === 'more_specifics';

                group.hidden = ! enabled;
                input.disabled = ! enabled;
            };

            document.querySelectorAll('.prefix-mode-field')
                .forEach((field) => {
                    field.addEventListener(
                        'change',
                        () => refreshPrefixMode(field)
                    );

                    refreshPrefixMode(field);
                });

            document.querySelectorAll('.copy-button').forEach((button) => {
                button.addEventListener('click', async () => {
                    const target = document.getElementById(
                        button.dataset.copyTarget
                    );

                    if (! target) {
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(
                            target.textContent.trim()
                        );

                        const original = button.textContent;
                        button.textContent = 'Copiado';

                        window.setTimeout(() => {
                            button.textContent = original;
                        }, 1500);
                    } catch (error) {
                        window.alert(
                            'Não foi possível copiar automaticamente.'
                        );
                    }
                });
            });
        })();
    </script>
@endsection
