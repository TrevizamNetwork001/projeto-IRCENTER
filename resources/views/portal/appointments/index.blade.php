@extends('layouts.portal')

@section('title', 'Meus agendamentos | Portal do Cliente')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Portal do cliente
            </div>

            <h1>Meus agendamentos</h1>

            <p>Acompanhe os atendimentos agendados para a sua empresa.</p>
        </div>
    </section>

    <section class="panel">
        @if ($appointments->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="registry" size="25"/>
                </div>

                <div>
                    <strong>Nenhum agendamento encontrado</strong>
                    <span>Ainda não há agendamentos vinculados à sua empresa.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Data/hora</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($appointments as $appointment)
                            @php
                                $class = match ($appointment->status) {
                                    'confirmed' => 'is-active',
                                    'cancelled' => 'is-inactive',
                                    default => '',
                                };
                            @endphp

                            <tr>
                                <td>
                                    <strong>{{ $appointment->eventType?->name ?? '—' }}</strong>
                                </td>

                                <td>
                                    {{ $appointment->scheduled_start_at
                                        ->timezone($appointment->timezone)
                                        ->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    <span class="status-pill {{ $class }}">
                                        {{ $appointment->statusLabel() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($appointments->hasPages())
                <div class="pagination-simple">
                    @if ($appointments->onFirstPage())
                        <span class="pagination-disabled">Anterior</span>
                    @else
                        <a href="{{ $appointments->previousPageUrl() }}">Anterior</a>
                    @endif

                    <span>
                        Página {{ $appointments->currentPage() }}
                        de {{ $appointments->lastPage() }}
                    </span>

                    @if ($appointments->hasMorePages())
                        <a href="{{ $appointments->nextPageUrl() }}">Próxima</a>
                    @else
                        <span class="pagination-disabled">Próxima</span>
                    @endif
                </div>
            @endif
        @endif
    </section>
@endsection
