@extends('layouts.app')

@section('title', 'Financeiro — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Gestão financeira
            </div>

            <h1>Financeiro</h1>

            <p>
                Contratos, faturamento e cobranças do IRCENTER.
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('finance.contracts.index') }}"
        >
            Ver contratos
        </a>
    </section>

    @if (! $financeEnabled)
        <section class="panel">
            <div class="empty-state">
                <div>
                    <strong>Módulo em modo protegido</strong>
                    <span>
                        FINANCE_ENABLED está desabilitado.
                        Consultas estão disponíveis, mas operações
                        de escrita pela interface permanecem bloqueadas.
                    </span>
                </div>
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Indicador</th>
                        <th>Valor</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Contratos cadastrados</td>
                        <td>{{ $contractsTotal }}</td>
                    </tr>

                    <tr>
                        <td>Contratos ativos</td>
                        <td>{{ $contractsActive }}</td>
                    </tr>

                    <tr>
                        <td>Faturas abertas</td>
                        <td>{{ $invoicesOpen }}</td>
                    </tr>

                    <tr>
                        <td>Total em aberto</td>
                        <td>
                            R$
                            {{ number_format(
                                (float) $openInvoiceTotal,
                                2,
                                ',',
                                '.'
                            ) }}
                        </td>
                    </tr>

                    <tr>
                        <td>Cobranças abertas</td>
                        <td>{{ $chargesOpen }}</td>
                    </tr>

                    <tr>
                        <td>Submissões incertas</td>
                        <td>{{ $chargesUnknown }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Configuração operacional</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <tbody>
                    <tr>
                        <th>Financeiro</th>
                        <td>
                            {{ $financeEnabled ? 'Habilitado' : 'Desabilitado' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Provider configurado</th>
                        <td>{{ $paymentProvider }}</td>
                    </tr>

                    <tr>
                        <th>Ambiente Efí</th>
                        <td>{{ $efiEnvironment }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="page-heading">
            <div>
                <h2>Contratos recentes</h2>
            </div>
        </div>

        @if ($recentContracts->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>Nenhum contrato financeiro</strong>
                    <span>
                        Os primeiros contratos aparecerão aqui.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Geração</th>
                            <th>Vencimento</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($recentContracts as $contract)
                            <tr>
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
                                        {{
                                            $contract
                                                ->client_trade_name_snapshot
                                            ?: $contract
                                                ->client_legal_name_snapshot
                                        }}
                                    </a>
                                </td>

                                <td>{{ $contract->status }}</td>
                                <td>Dia {{ $contract->generation_day }}</td>
                                <td>Dia {{ $contract->due_day }}</td>
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
                <h2>Faturas recentes</h2>
            </div>
        </div>

        @if ($recentInvoices->isEmpty())
            <div class="empty-state">
                <div>
                    <strong>Nenhuma fatura</strong>
                    <span>
                        As faturas geradas aparecerão aqui.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Competência</th>
                            <th>Vencimento</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($recentInvoices as $invoice)
                            <tr>
                                <td>
                                    {{
                                        $invoice
                                            ->client_trade_name_snapshot
                                        ?: $invoice
                                            ->client_legal_name_snapshot
                                    }}
                                </td>

                                <td>
                                    {{
                                        $invoice->competence_month
                                            ?->format('m/Y')
                                    }}
                                </td>

                                <td>
                                    {{
                                        $invoice->due_on
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

                                <td>{{ $invoice->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
