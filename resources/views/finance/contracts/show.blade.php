@extends('layouts.app')

@section('title', 'Contrato financeiro — IRCENTER')

@section('content')
    @php
        $statusLabel = match ($contract->status) {
            'draft' => 'Rascunho',
            'active' => 'Ativo',
            'suspended' => 'Suspenso',
            'ended' => 'Encerrado',
            default => $contract->status,
        };
    @endphp

    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Contrato financeiro
            </div>

            <h1>
                {{
                    $contract->client_trade_name_snapshot
                    ?: $contract->client_legal_name_snapshot
                }}
            </h1>

            <p class="table-mono">
                {{ $contract->public_id }}
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('finance.contracts.index') }}"
        >
            Voltar
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
                        <th>Cliente</th>
                        <td>
                            {{ $contract->client_legal_name_snapshot }}
                        </td>
                    </tr>

                    <tr>
                        <th>Código</th>
                        <td class="table-mono">
                            {{ $contract->client_code_snapshot }}
                        </td>
                    </tr>

                    <tr>
                        <th>Documento</th>
                        <td class="table-mono">
                            {{ $contract->client_document_snapshot ?: '—' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <td>{{ $statusLabel }}</td>
                    </tr>

                    <tr>
                        <th>Periodicidade</th>
                        <td>Mensal</td>
                    </tr>

                    <tr>
                        <th>Geração</th>
                        <td>Dia {{ $contract->generation_day }}</td>
                    </tr>

                    <tr>
                        <th>Vencimento</th>
                        <td>Dia {{ $contract->due_day }}</td>
                    </tr>

                    <tr>
                        <th>E-mail específico</th>
                        <td>
                            {{
                                $contract->billing_email_override
                                ?: 'Contato financeiro do cliente'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Início</th>
                        <td>
                            {{
                                $contract->starts_on
                                    ?->format('d/m/Y')
                                ?: '—'
                            }}
                        </td>
                    </tr>

                    <tr>
                        <th>Fim</th>
                        <td>
                            {{
                                $contract->ends_on
                                    ?->format('d/m/Y')
                                ?: '—'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if (
            $financeEnabled
            && auth()->user()->isAdministrator()
        )
            <div class="form-actions">
                @if (
                    in_array(
                        $contract->status,
                        ['draft', 'suspended'],
                        true
                    )
                )
                    <form
                        method="POST"
                        action="{{
                            route(
                                'finance.contracts.activate',
                                $contract
                            )
                        }}"
                    >
                        @csrf

                        <button
                            class="button button-primary"
                            type="submit"
                        >
                            Ativar contrato
                        </button>
                    </form>
                @endif

                @if ($contract->status === 'active')
                    <form
                        method="POST"
                        action="{{
                            route(
                                'finance.contracts.suspend',
                                $contract
                            )
                        }}"
                    >
                        @csrf

                        <button
                            class="button button-secondary"
                            type="submit"
                        >
                            Suspender contrato
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Itens do contrato</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Descrição</th>
                        <th>Qtd.</th>
                        <th>Valor unitário</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($contract->items as $item)
                        <tr>
                            <td>{{ $item->service_code ?: '—' }}</td>
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
                                {{ $item->active ? 'Ativo' : 'Inativo' }}
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
                <h2>Faturas</h2>
            </div>

            @if (
                $financeEnabled
                && auth()->user()->isAdministrator()
                && $contract->status === 'active'
            )
                <form
                    class="form-actions"
                    method="POST"
                    action="{{
                        route(
                            'finance.contracts.invoices.generate',
                            $contract
                        )
                    }}"
                >
                    @csrf

                    <input
                        class="form-control"
                        type="month"
                        name="competence"
                        value="{{
                            old(
                                'competence',
                                app(\App\Support\BusinessClock::class)
                                    ->now()
                                    ->format('Y-m')
                            )
                        }}"
                        required
                    >

                    <button
                        class="button button-primary"
                        type="submit"
                    >
                        Gerar fatura
                    </button>
                </form>
            @endif
        </div>

        @if ($invoices->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>Nenhuma fatura gerada</strong>
                    <span>
                        Use o seletor de competência acima
                        para gerar a primeira fatura.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Competência</th>
                            <th>Emissão</th>
                            <th>Vencimento</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a
                                        class="table-primary-link"
                                        href="{{
                                            route(
                                                'finance.invoices.show',
                                                $invoice
                                            )
                                        }}"
                                    >
                                        {{
                                            $invoice
                                                ->competence_month
                                                ?->format('m/Y')
                                        }}
                                    </a>
                                </td>

                                <td>
                                    {{
                                        $invoice
                                            ->issued_on
                                            ?->format('d/m/Y')
                                    }}
                                </td>

                                <td>
                                    {{
                                        $invoice
                                            ->due_on
                                            ?->format('d/m/Y')
                                    }}
                                </td>

                                <td>
                                    R$
                                    {{
                                        number_format(
                                            (float) $invoice->total,
                                            2,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td>
                                    {{
                                        match ($invoice->status) {
                                            'draft' => 'Rascunho',
                                            'open' => 'Aberta',
                                            'partially_paid' =>
                                                'Parcialmente paga',
                                            'paid' => 'Paga',
                                            'canceled' => 'Cancelada',
                                            default => $invoice->status,
                                        }
                                    }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
