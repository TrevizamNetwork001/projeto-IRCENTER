@extends('layouts.app')

@section('title', 'Agenda — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Operação
            </div>

            <h1>Agenda</h1>

            <p>Agendamentos e disponibilidade.</p>
        </div>

        <div class="page-actions">
            <a class="button button-secondary" href="{{ route('scheduling.admin.export', request()->query()) }}">
                Exportar CSV
            </a>

            @if (auth()->user()->canOperate())
                <a class="button button-primary" href="{{ route('scheduling.admin.appointments.create') }}">
                    Novo agendamento
                </a>
            @endif
        </div>
    </section>

    @include('scheduling.admin.nav')

    <section class="metrics-grid">
        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon cyan">
                    <x-icon name="calendar"/>
                </div>
            </div>

            <div class="metric-value">{{ $today }}</div>
            <div class="metric-label">Hoje</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon violet">
                    <x-icon name="calendar"/>
                </div>
            </div>

            <div class="metric-value">{{ $nextSeven }}</div>
            <div class="metric-label">Próximos 7 dias</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <x-icon name="calendar"/>
                </div>
            </div>

            <div class="metric-value">{{ $cancelled }}</div>
            <div class="metric-label">Cancelados no mês</div>
        </div>

        <div class="metric-card">
            <div class="metric-card-header">
                <div class="metric-icon amber">
                    <x-icon name="calendar"/>
                </div>
            </div>

            <div class="metric-value">{{ $noShow }}</div>
            <div class="metric-label">No-show</div>
        </div>
    </section>

    <section class="panel">
        <form method="GET" class="filter-bar">
            <div class="filter-search">
                <x-icon name="search" size="18"/>

                <input
                    name="search"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Nome ou e-mail..."
                >
            </div>

            <select class="filter-select" name="status" aria-label="Filtrar por status">
                <option value="">Todos os status</option>

                @foreach ([
                    'scheduled' => 'Agendado',
                    'confirmed' => 'Confirmado',
                    'cancelled' => 'Cancelado',
                    'completed' => 'Concluído',
                    'no_show' => 'Não compareceu',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select class="filter-select" name="event_type" aria-label="Filtrar por serviço">
                <option value="">Todos os serviços</option>

                @foreach ($eventTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) request('event_type') === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>

            <input class="filter-select" type="date" name="from" value="{{ request('from') }}">
            <input class="filter-select" type="date" name="to" value="{{ request('to') }}">

            <button class="button button-secondary" type="submit">
                Filtrar
            </button>

            @if (request()->anyFilled(['search', 'status', 'event_type', 'from', 'to']))
                <a class="button button-ghost" href="{{ route('scheduling.admin.index') }}">
                    Limpar
                </a>
            @endif
        </form>

        @if ($appointments->isEmpty())
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="calendar" size="25"/>
                </div>

                <div>
                    <strong>Nenhum agendamento encontrado</strong>
                    <span>Ajuste os filtros de busca ou crie um novo agendamento.</span>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visitante</th>
                            <th>Serviço</th>
                            <th>Data/hora</th>
                            <th>Status</th>
                            <th class="table-actions-column">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($appointments as $appointment)
                            @php
                                $statusClass = match ($appointment->status) {
                                    'confirmed', 'completed' => 'is-active',
                                    'cancelled', 'no_show' => 'is-inactive',
                                    default => '',
                                };
                            @endphp

                            <tr>
                                <td>
                                    <div class="table-identity">
                                        <span class="user-avatar user-avatar-small" aria-hidden="true">
                                            <span class="user-avatar-initials">
                                                {{ Illuminate\Support\Str::of($appointment->guest_name)->trim()->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join('') }}
                                            </span>
                                        </span>

                                        <div class="table-identity-text">
                                            <strong>{{ $appointment->guest_name }}</strong>
                                            <span class="table-secondary-text">{{ $appointment->guest_email }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    {{ $appointment->eventType->name }}
                                    <span class="table-secondary-text">{{ $appointment->eventType->duration_minutes }} min</span>
                                </td>

                                <td>
                                    {{ $appointment->scheduled_start_at->setTimezone($appointment->timezone)->format('d/m/Y H:i') }}
                                </td>

                                <td>
                                    <span class="status-pill {{ $statusClass }}">
                                        {{ $appointment->statusLabel() }}
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="table-icon-button"
                                            href="{{ route('scheduling.admin.appointments.show', $appointment) }}"
                                            aria-label="Ver detalhes de {{ $appointment->guest_name }}"
                                            title="Detalhes"
                                        >
                                            <x-icon name="eye" size="15"/>
                                        </a>
                                    </div>
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
