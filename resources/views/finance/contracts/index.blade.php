@extends('layouts.app')

@section('title', 'Contratos financeiros — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Financeiro
            </div>

            <h1>Contratos financeiros</h1>

            <p>
                Contratos recorrentes vinculados aos clientes
                do IRCENTER.
            </p>
        </div>

        @if (
            $financeEnabled
            && auth()->user()->isAdministrator()
        )
            <a
                class="button button-primary"
                href="{{ route('finance.contracts.create') }}"
            >
                Novo contrato
            </a>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (! $financeEnabled)
        <section class="panel finance-safety-panel">
            <div class="empty-state finance-safety-state">
                <div>
                    <strong>Operações bloqueadas</strong>
                    <span>
                        FINANCE_ENABLED está desabilitado.
                    </span>
                </div>
            </div>
        </section>
    @endif

    <section class="panel">
        <form
            class="filter-bar"
            method="GET"
            action="{{ route('finance.contracts.index') }}"
        >
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Buscar cliente, código, documento ou ID"
                >
            </div>

            <select class="filter-select" name="status">
                <option value="all" @selected($status === 'all')>
                    Todos os status
                </option>

                <option value="draft" @selected($status === 'draft')>
                    Rascunho
                </option>

                <option value="active" @selected($status === 'active')>
                    Ativo
                </option>

                <option
                    value="suspended"
                    @selected($status === 'suspended')
                >
                    Suspenso
                </option>

                <option value="ended" @selected($status === 'ended')>
                    Encerrado
                </option>
            </select>

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if ($search !== '' || $status !== 'all')
                <a
                    class="button button-ghost"
                    href="{{ route('finance.contracts.index') }}"
                >
                    Limpar
                </a>
            @endif
        </form>

        @if ($contracts->isEmpty())
            <div class="empty-state empty-state-large finance-list-empty">
                <div>
                    <strong>Nenhum contrato encontrado</strong>
                    <span>
                        Cadastre o primeiro contrato ou altere
                        os filtros.
                    </span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Itens</th>
                            <th>Geração</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th class="table-actions-column">
                                Ações
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($contracts as $contract)
                            @php
                                $statusLabel = match (
                                    $contract->status
                                ) {
                                    'draft' => 'Rascunho',
                                    'active' => 'Ativo',
                                    'suspended' => 'Suspenso',
                                    'ended' => 'Encerrado',
                                    default => $contract->status,
                                };
                            @endphp

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

                                    <span
                                        class="table-secondary-text table-mono"
                                    >
                                        {{
                                            $contract
                                                ->client_code_snapshot
                                        }}
                                    </span>
                                </td>

                                <td>{{ $contract->items_count }}</td>

                                <td>
                                    Dia {{ $contract->generation_day }}
                                </td>

                                <td>
                                    Dia {{ $contract->due_day }}
                                </td>

                                <td>
                                    <span
                                        class="status-pill {{
                                            $contract->status === 'active'
                                                ? 'is-active'
                                                : 'is-inactive'
                                        }}"
                                    >
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-action-link"
                                            href="{{
                                                route(
                                                    'finance.contracts.show',
                                                    $contract
                                                )
                                            }}"
                                        >
                                            Abrir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($contracts->hasPages())
                <div class="pagination-simple">
                    @if ($contracts->onFirstPage())
                        <span class="pagination-disabled">
                            Anterior
                        </span>
                    @else
                        <a href="{{ $contracts->previousPageUrl() }}">
                            Anterior
                        </a>
                    @endif

                    <span>
                        Página {{ $contracts->currentPage() }}
                        de {{ $contracts->lastPage() }}
                    </span>

                    @if ($contracts->hasMorePages())
                        <a href="{{ $contracts->nextPageUrl() }}">
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
