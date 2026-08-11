@extends('layouts.app')

@section('title', 'Fatura — IRCENTER')

@section('content')
    @php
        $statusLabel = match ($invoice->status) {
            'draft' => 'Rascunho',
            'open' => 'Aberta',
            'partially_paid' => 'Parcialmente paga',
            'paid' => 'Paga',
            'canceled' => 'Cancelada',
            default => $invoice->status,
        };
    @endphp

    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Fatura financeira
            </div>

            <h1>
                {{
                    $invoice->client_trade_name_snapshot
                    ?: $invoice->client_legal_name_snapshot
                }}
            </h1>

            <p class="table-mono">
                {{ $invoice->public_id }}
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('finance.invoices.index') }}"
        >
            Voltar às faturas
        </a>
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <section class="panel">
            <div class="field-error">
                {{ session('error') }}
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="table-responsive">
            <table class="data-table">
                <tbody>
                    <tr>
                        <th>Status</th>
                        <td>{{ $statusLabel }}</td>
                    </tr>

                    <tr>
                        <th>Competência</th>
                        <td>
                            {{
                                $invoice->competence_month
                                    ?->format('m/Y')
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Emissão</th>
                        <td>
                            {{
                                $invoice->issued_on
                                    ?->format('d/m/Y')
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Vencimento</th>
                        <td>
                            {{
                                $invoice->due_on
                                    ?->format('d/m/Y')
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Origem</th>
                        <td>
                            {{
                                $invoice->source === 'recurring'
                                    ? 'Contrato recorrente'
                                    : 'Avulsa'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Subtotal</th>
                        <td>
                            R$
                            {{
                                number_format(
                                    (float) $invoice->subtotal,
                                    2,
                                    ',',
                                    '.'
                                )
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Desconto</th>
                        <td>
                            R$
                            {{
                                number_format(
                                    (float) $invoice->discount,
                                    2,
                                    ',',
                                    '.'
                                )
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Total</th>
                        <td>
                            <strong>
                                R$
                                {{
                                    number_format(
                                        (float) $invoice->total,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </strong>
                        </td>
                    </tr>

                    @if ($contract)
                        <tr>
                            <th>Contrato financeiro</th>
                            <td>
                                <a
                                    class="table-primary-link"
                                    href="{{
                                        route(
                                            'finance.contracts.show',
                                            $contract
                                        )
                                    }}"
                                >
                                    Abrir contrato
                                </a>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Pagador congelado na emissão</h2>

                <p>
                    Estes dados não mudam se o cadastro
                    atual do cliente for alterado.
                </p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <tbody>
                    <tr>
                        <th>Razão social</th>
                        <td>
                            {{ $invoice->client_legal_name_snapshot }}
                        </td>
                    </tr>

                    <tr>
                        <th>Documento</th>
                        <td class="table-mono">
                            {{
                                $invoice->client_document_snapshot
                                ?: '—'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>E-mail financeiro</th>
                        <td>
                            {{
                                $invoice->billing_email_snapshot
                                ?: '—'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Telefone</th>
                        <td>
                            {{
                                $invoice->client_phone_snapshot
                                ?: '—'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Endereço</th>
                        <td>
                            {{
                                collect([
                                    $invoice
                                        ->client_street_snapshot,
                                    $invoice
                                        ->client_address_number_snapshot,
                                    $invoice
                                        ->client_address_complement_snapshot,
                                    $invoice
                                        ->client_district_snapshot,
                                    $invoice
                                        ->client_city_snapshot,
                                    $invoice
                                        ->client_state_snapshot,
                                    $invoice
                                        ->client_postal_code_snapshot,
                                ])
                                    ->filter()
                                    ->implode(', ')
                                ?: '—'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Itens</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Descrição</th>
                        <th>Quantidade</th>
                        <th>Unitário</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>
                                {{ $item->service_code ?: '—' }}
                            </td>

                            <td>{{ $item->description }}</td>

                            <td>{{ $item->quantity }}</td>

                            <td>
                                R$
                                {{
                                    number_format(
                                        (float) $item->unit_amount,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </td>

                            <td>
                                R$
                                {{
                                    number_format(
                                        (float) $item->line_total,
                                        2,
                                        ',',
                                        '.'
                                    )
                                }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @php
        $blockingCharge = $charges->first(
            fn ($candidate) => in_array(
                $candidate->status,
                [
                    'submitting',
                    'submission_unknown',
                    'created',
                    'open',
                    'paid',
                    'overdue',
                    'failed',
                ],
                true
            )
        );

        $blockingChargeIsUncertain =
            $blockingCharge
            && in_array(
                $blockingCharge->status,
                [
                    'submitting',
                    'submission_unknown',
                ],
                true
            );

        $blockingChargeRequiresReview =
            $blockingCharge
            && $blockingCharge->status === 'failed';

        $isEfiHomologation =
            $paymentProviderKey === 'efi'
            && $efiEnvironment === 'homologation';

        $isEfiWebBlocked =
            $paymentProviderKey === 'efi'
            && $efiEnvironment !== 'homologation';
    @endphp

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Cobranças</h2>

                <p>
                    Provider:
                    <strong>
                        @if ($paymentProviderKey === 'fake')
                            Fake · simulador local
                        @elseif (
                            $paymentProviderKey === 'efi'
                            && $efiEnvironment === 'homologation'
                        )
                            Efí · Homologação
                        @elseif ($paymentProviderLive)
                            {{ $paymentProviderKey }}
                            · PRODUÇÃO BLOQUEADA NA WEB
                        @else
                            {{ $paymentProviderKey }}
                        @endif
                    </strong>
                </p>
            </div>
        </div>

        @if ($isEfiHomologation)
            <div class="finance-homologation-banner">
                <strong>
                    EFÍ — HOMOLOGAÇÃO
                </strong>

                <span>
                    Esta cobrança será enviada ao ambiente
                    de homologação da Efí. Nenhuma emissão
                    em produção está liberada pela Web.
                </span>
            </div>
        @endif

        @if (
            $paymentProviderLive
            || $isEfiWebBlocked
        )
            <div class="empty-state">
                <div>
                    <strong>
                        Efí fora da homologação bloqueada
                    </strong>

                    <span>
                        Esta versão da interface somente
                        permite Efí em homologação.
                        Produção permanece bloqueada
                        independentemente das demais flags.
                    </span>
                </div>
            </div>
        @elseif ($blockingChargeIsUncertain)
            <div class="finance-uncertain-banner">
                <strong>
                    Submissão de cobrança incerta
                </strong>

                <span>
                    Não gere outra cobrança para esta
                    fatura. Use a ação Reconciliar na
                    cobrança abaixo quando disponível.
                </span>
            </div>
        @elseif ($blockingChargeRequiresReview)
            <div class="finance-charge-existing-banner">
                <strong>
                    Cobrança existente requer revisão
                </strong>

                <span>
                    A cobrança anterior pode representar uma
                    obrigação externa. Nova emissão permanece
                    bloqueada e não deve ser tentada novamente.
                </span>
            </div>
        @elseif ($blockingCharge)
            <div class="finance-charge-existing-banner">
                <strong>
                    Cobrança já existente
                </strong>

                <span>
                    Esta fatura já possui uma cobrança
                    operacional. Nova emissão pela Web
                    permanece bloqueada.
                </span>
            </div>
        @elseif (
            $financeEnabled
            && auth()->user()->isAdministrator()
            && $invoice->status === 'open'
            && $availablePaymentMethods !== []
        )
            <form
                class="form-grid"
                method="POST"
                action="{{
                    route(
                        'finance.invoices.charges.store',
                        $invoice
                    )
                }}"
            >
                @csrf

                <div class="field-group">
                    <label for="method">
                        Método de cobrança
                    </label>

                    <select
                        id="method"
                        class="form-control"
                        name="method"
                        required
                    >
                        @foreach (
                            $availablePaymentMethods
                            as $method
                        )
                            <option
                                value="{{ $method }}"
                                @selected(
                                    old('method')
                                    === $method
                                )
                            >
                                @switch($method)
                                    @case('boleto')
                                        Boleto
                                        @break

                                    @case('pix')
                                        Pix
                                        @break

                                    @case('boleto_pix')
                                        Boleto + Pix
                                        @break

                                    @default
                                        {{ $method }}
                                @endswitch
                            </option>
                        @endforeach
                    </select>

                    @error('method')
                        <div class="field-error">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                @if ($paymentProviderKey !== 'fake')
                    <div
                        class="field-group field-span-2"
                    >
                        <label>
                            <input
                                type="checkbox"
                                name="confirm_provider_submission"
                                value="1"
                                required
                            >

                            Confirmo a emissão desta cobrança
                            no ambiente
                            <strong>
                                {{
                                    $paymentProviderKey
                                    === 'efi'
                                    ? 'Efí Homologação'
                                    : $paymentProviderKey
                                }}
                            </strong>.
                        </label>

                        @error(
                            'confirm_provider_submission'
                        )
                            <div class="field-error">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    @if ($isEfiHomologation)
                        <div
                            class="field-group field-span-2"
                        >
                            <label
                                for="confirm_provider_phrase"
                            >
                                Confirmação forte
                            </label>

                            <input
                                id="confirm_provider_phrase"
                                class="form-control table-mono"
                                type="text"
                                name="confirm_provider_phrase"
                                value=""
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="EMITIR EFI HOMOLOGACAO"
                                required
                            >

                            <small class="field-help">
                                Digite exatamente:
                                <strong>
                                    EMITIR EFI HOMOLOGACAO
                                </strong>
                            </small>

                            @error(
                                'confirm_provider_phrase'
                            )
                                <div class="field-error">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    @endif
                @endif

                <div
                    class="form-actions field-span-2"
                >
                    <button
                        class="button button-primary"
                        type="submit"
                    >
                        Gerar cobrança
                    </button>
                </div>
            </form>
        @elseif (! $financeEnabled)
            <div class="empty-state">
                <div>
                    <strong>
                        Emissão bloqueada
                    </strong>

                    <span>
                        FINANCE_ENABLED está desabilitado.
                    </span>
                </div>
            </div>
        @endif

        @if ($charges->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>
                        Nenhuma cobrança associada
                    </strong>

                    <span>
                        Nenhuma submissão foi registrada
                        para esta fatura.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Método</th>
                            <th>Status</th>
                            <th>ID provider</th>
                            <th>Valor</th>
                            <th>Pagamento</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($charges as $charge)
                            <tr>
                                <td>
                                    {{ $charge->provider }}
                                </td>

                                <td>
                                    @switch($charge->method)
                                        @case('boleto')
                                            Boleto
                                            @break

                                        @case('pix')
                                            Pix
                                            @break

                                        @case('boleto_pix')
                                            Boleto + Pix
                                            @break

                                        @default
                                            {{ $charge->method }}
                                    @endswitch
                                </td>

                                <td>
                                    {{
                                        match (
                                            $charge->status
                                        ) {
                                            'submitting' =>
                                                'Enviando / incerto',
                                            'submission_unknown' =>
                                                'Submissão incerta',
                                            'created' =>
                                                'Criada',
                                            'open' =>
                                                'Aberta',
                                            'paid' =>
                                                'Paga',
                                            'overdue' =>
                                                'Vencida',
                                            'canceled' =>
                                                'Cancelada',
                                            'failed' =>
                                                'Falhou',
                                            default =>
                                                $charge->status,
                                        }
                                    }}
                                </td>

                                <td class="table-mono">
                                    {{
                                        $charge
                                            ->provider_charge_id
                                        ?: '—'
                                    }}
                                </td>

                                <td>
                                    R$
                                    {{
                                        number_format(
                                            (float)
                                                $charge->amount,
                                            2,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td>
                                    @if (
                                        $charge
                                            ->provider_checkout_url
                                        && str_starts_with(
                                            $charge
                                                ->provider_checkout_url,
                                            'https://'
                                        )
                                    )
                                        <a
                                            class="table-primary-link"
                                            href="{{
                                                $charge
                                                    ->provider_checkout_url
                                            }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Abrir cobrança
                                        </a>
                                    @endif

                                    @if (
                                        $charge
                                            ->provider_billet_url
                                        && filter_var(
                                            $charge
                                                ->provider_billet_url,
                                            FILTER_VALIDATE_URL
                                        )
                                        && str_starts_with(
                                            strtolower(
                                                $charge
                                                    ->provider_billet_url
                                            ),
                                            'https://'
                                        )
                                    )
                                        <a
                                            class="table-primary-link"
                                            href="{{ $charge->provider_billet_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Abrir boleto
                                        </a>
                                    @endif

                                    @if (
                                        $charge
                                            ->provider_billet_pdf_url
                                        && filter_var(
                                            $charge
                                                ->provider_billet_pdf_url,
                                            FILTER_VALIDATE_URL
                                        )
                                        && str_starts_with(
                                            strtolower(
                                                $charge
                                                    ->provider_billet_pdf_url
                                            ),
                                            'https://'
                                        )
                                    )
                                        <a
                                            class="table-primary-link"
                                            href="{{ $charge->provider_billet_pdf_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Baixar PDF
                                        </a>
                                    @endif

                                    @if ($charge->provider_barcode)
                                        <div class="field-group">
                                            <label>
                                                Código/linha retornada
                                            </label>

                                            <textarea
                                                class="form-control table-mono"
                                                rows="2"
                                                readonly
                                            >{{ $charge->provider_barcode }}</textarea>
                                        </div>
                                    @endif

                                    @if (
                                        $charge
                                            ->provider_pix_copy_paste
                                    )
                                        <div
                                            class="field-group"
                                        >
                                            <label>
                                                Pix copia e cola
                                            </label>

                                            <textarea
                                                class="form-control table-mono"
                                                rows="3"
                                                readonly
                                            >{{ $charge->provider_pix_copy_paste }}</textarea>
                                        </div>
                                    @elseif (
                                        ! $charge
                                            ->provider_checkout_url
                                        && ! $charge
                                            ->provider_billet_url
                                        && ! $charge
                                            ->provider_billet_pdf_url
                                        && ! $charge
                                            ->provider_barcode
                                    )
                                        —
                                    @endif
                                </td>

                                <td>
                                    @if (
                                        $reconciliationAvailable
                                        && $charge->provider
                                            === $paymentProviderKey
                                        && in_array(
                                            $charge->status,
                                            [
                                                'submitting',
                                                'submission_unknown',
                                            ],
                                            true
                                        )
                                        && ! $charge
                                            ->provider_charge_id
                                        && $financeEnabled
                                        && auth()
                                            ->user()
                                            ->isAdministrator()
                                    )
                                        <form
                                            method="POST"
                                            action="{{
                                                route(
                                                    'finance.charges.reconcile',
                                                    $charge
                                                )
                                            }}"
                                        >
                                            @csrf

                                            <button
                                                class="button button-secondary"
                                                type="submit"
                                            >
                                                Reconciliar
                                            </button>
                                        </form>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Pagamentos</h2>
                <p>Recebimentos confirmados pelo provider.</p>
            </div>
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>Nenhum pagamento confirmado</strong>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Valor recebido</th>
                            <th>Data do pagamento</th>
                            <th>Provider</th>
                            <th>Referência</th>
                            <th>Charge</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>Pago</td>
                                <td>
                                    R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}
                                </td>
                                <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ strtoupper($payment->provider) }}</td>
                                <td class="table-mono">{{ $payment->provider_payment_id }}</td>
                                <td class="table-mono">{{ $payment->charge_id }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Timeline financeira</h2>
                <p>Eventos auditáveis da fatura, cobranças e pagamentos.</p>
            </div>
        </div>

        @if ($timeline->isEmpty())
            <div class="empty-state">
                <div><strong>Nenhum evento financeiro</strong></div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Evento</th>
                            <th>Entidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($timeline as $event)
                            @php
                                $eventLabel = match ($event->action) {
                                    'invoice.generated' => 'Invoice gerada',
                                    'charge.reserved' => 'Cobrança reservada',
                                    'charge.submission_unknown' => 'Envio com resultado desconhecido',
                                    'charge.reconciled' => 'Cobrança reconciliada',
                                    'charge.synced' => 'Cobrança atualizada pelo provider',
                                    'payment_webhook.received' => 'Webhook recebido',
                                    'payment_webhook.processed' => 'Webhook processado',
                                    'charge.paid' => 'Pagamento confirmado',
                                    'payment.created' => 'Pagamento registrado',
                                    'invoice.paid' => 'Invoice paga',
                                    default => $event->action,
                                };
                            @endphp
                            <tr>
                                <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $eventLabel }}</td>
                                <td>{{ $event->entity_type }} #{{ $event->entity_id }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
