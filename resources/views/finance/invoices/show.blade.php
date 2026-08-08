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

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Cobranças</h2>

                <p>
                    Somente visualização nesta fase.
                </p>
            </div>
        </div>

        @if ($charges->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>Nenhuma cobrança associada</strong>
                    <span>
                        A emissão de boleto/Pix pela Web
                        será liberada em etapa controlada.
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
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($charges as $charge)
                            <tr>
                                <td>{{ $charge->provider }}</td>

                                <td>{{ $charge->method }}</td>

                                <td>{{ $charge->status }}</td>

                                <td class="table-mono">
                                    {{
                                        $charge->provider_charge_id
                                        ?: '—'
                                    }}
                                </td>

                                <td>
                                    R$
                                    {{
                                        number_format(
                                            (float) $charge->amount,
                                            2,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td>
                                    @if (
                                        $charge->provider_checkout_url
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
                                    @elseif (
                                        $charge
                                            ->provider_pix_copy_paste
                                    )
                                        Pix disponível
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
@endsection
