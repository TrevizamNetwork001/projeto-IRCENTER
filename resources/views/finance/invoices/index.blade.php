@extends('layouts.app')

@section('title', 'Faturas — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Financeiro
            </div>

            <h1>Faturas</h1>

            <p>
                Obrigações financeiras geradas pelos contratos
                recorrentes do IRCENTER.
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('finance.dashboard') }}"
        >
            Dashboard financeiro
        </a>
    </section>

    @if (! $financeEnabled)
        <section class="panel finance-safety-panel">
            <div class="empty-state finance-safety-state">
                <div>
                    <strong>Modo somente leitura</strong>
                    <span>
                        FINANCE_ENABLED está desabilitado.
                        Nenhuma nova fatura pode ser gerada.
                    </span>
                </div>
            </div>
        </section>
    @endif

    <section class="panel">
        <form
            class="filter-bar"
            method="GET"
            action="{{ route('finance.invoices.index') }}"
        >
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Cliente, código, documento ou ID"
                >
            </div>

            <input
                class="filter-select"
                type="month"
                name="competence"
                value="{{ $competence }}"
                aria-label="Competência"
            >

            <select class="filter-select" name="status">
                <option value="all" @selected($status === 'all')>
                    Todos os status
                </option>

                <option value="draft" @selected($status === 'draft')>
                    Rascunho
                </option>

                <option value="open" @selected($status === 'open')>
                    Aberta
                </option>

                <option
                    value="partially_paid"
                    @selected($status === 'partially_paid')
                >
                    Parcialmente paga
                </option>

                <option value="paid" @selected($status === 'paid')>
                    Paga
                </option>

                <option
                    value="canceled"
                    @selected($status === 'canceled')
                >
                    Cancelada
                </option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if (
                $search !== ''
                || $status !== 'all'
                || $competence !== ''
            )
                <a
                    class="button button-ghost"
                    href="{{ route('finance.invoices.index') }}"
                >
                    Limpar
                </a>
            @endif
        </form>

        @if ($invoices->isEmpty())
            <div class="empty-state empty-state-large finance-list-empty">
                <div>
                    <strong>Nenhuma fatura encontrada</strong>
                    <span>
                        Gere uma fatura a partir de um contrato
                        ativo ou altere os filtros.
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
                            <th>Emissão</th>
                            <th>Vencimento</th>
                            <th>Itens</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th class="table-actions-column">
                                Ações
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($invoices as $invoice)
                            @php
                                $statusLabel = match (
                                    $invoice->status
                                ) {
                                    'draft' => 'Rascunho',
                                    'open' => 'Aberta',
                                    'partially_paid' =>
                                        'Parcialmente paga',
                                    'paid' => 'Paga',
                                    'canceled' => 'Cancelada',
                                    default => $invoice->status,
                                };
                            @endphp

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
                                                ->client_trade_name_snapshot
                                            ?: $invoice
                                                ->client_legal_name_snapshot
                                        }}
                                    </a>

                                    <span
                                        class="table-secondary-text table-mono"
                                    >
                                        {{
                                            $invoice
                                                ->client_code_snapshot
                                        }}
                                    </span>
                                </td>

                                <td>
                                    {{
                                        $invoice->competence_month
                                            ?->format('m/Y')
                                    }}
                                </td>

                                <td>
                                    {{
                                        $invoice->issued_on
                                            ?->format('d/m/Y')
                                    }}
                                </td>

                                <td>
                                    {{
                                        $invoice->due_on
                                            ?->format('d/m/Y')
                                    }}
                                </td>

                                <td>{{ $invoice->items_count }}</td>

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
                                    <span
                                        class="status-pill {{
                                            $invoice->status === 'paid'
                                                ? 'is-active'
                                                : 'is-inactive'
                                        }}"
                                    >
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td>
                                    <a
                                        class="table-action-link"
                                        href="{{
                                            route(
                                                'finance.invoices.show',
                                                $invoice
                                            )
                                        }}"
                                    >
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="pagination-simple">
                    @if ($invoices->onFirstPage())
                        <span class="pagination-disabled">
                            Anterior
                        </span>
                    @else
                        <a href="{{ $invoices->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $invoices->currentPage() }}
                        de {{ $invoices->lastPage() }}
                    </span>

                    @if ($invoices->hasMorePages())
                        <a href="{{ $invoices->nextPageUrl() }}">
                            Próxima
                        </a>
                    @else
                        <span class="pagination-disabled">
                            Próxima
                        </span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
