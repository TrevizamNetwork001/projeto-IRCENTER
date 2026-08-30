@extends('layouts.portal')

@section('title', 'Minhas faturas | Portal do Cliente')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Portal do cliente
            </div>

            <h1>Minhas faturas</h1>

            <p>Acompanhe o status e o vencimento das suas faturas.</p>
        </div>
    </section>

    <section class="panel">
        @if ($invoices->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="report" size="25"/>
                </div>

                <div>
                    <strong>Nenhuma fatura encontrada</strong>
                    <span>Ainda não há faturas emitidas para a sua empresa.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Status</th>
                            <th>Vencimento</th>
                            <th>Emissão</th>
                            <th>Valor</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($invoices as $invoice)
                            @php
                                $label = match ($invoice->status) {
                                    'paid' => 'Pago',
                                    'canceled' => 'Cancelado',
                                    'partially_paid' => 'Pagamento parcial',
                                    default => 'Aguardando pagamento',
                                };

                                $class = match ($invoice->status) {
                                    'paid' => 'is-active',
                                    'canceled' => 'is-inactive',
                                    default => '',
                                };
                            @endphp

                            <tr>
                                <td class="table-mono">#{{ $invoice->id }}</td>

                                <td>
                                    <span class="status-pill {{ $class }}">
                                        {{ $label }}
                                    </span>
                                </td>

                                <td>{{ $invoice->due_on?->format('d/m/Y') }}</td>
                                <td>{{ $invoice->issued_on?->format('d/m/Y') }}</td>

                                <td>
                                    <strong>
                                        R$ {{ number_format((float) $invoice->total, 2, ',', '.') }}
                                    </strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="pagination-simple">
                    @if ($invoices->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $invoices->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $invoices->currentPage() }}
                        de {{ $invoices->lastPage() }}
                    </span>

                    @if ($invoices->hasMorePages())
                        <a href="{{ $invoices->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
