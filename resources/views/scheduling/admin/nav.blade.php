<nav class="tab-nav" aria-label="Agenda">
    <a
        href="{{ route('scheduling.admin.index') }}"
        class="{{ request()->routeIs('scheduling.admin.index') || request()->routeIs('scheduling.admin.appointments.*') ? 'is-active' : '' }}"
    >
        Agendamentos
    </a>

    <a
        href="{{ route('scheduling.admin.calendar', ['view' => 'day']) }}"
        class="{{ request()->routeIs('scheduling.admin.calendar') && request('view') === 'day' ? 'is-active' : '' }}"
    >
        Dia
    </a>

    <a
        href="{{ route('scheduling.admin.calendar', ['view' => 'week']) }}"
        class="{{ request()->routeIs('scheduling.admin.calendar') && request('view') === 'week' ? 'is-active' : '' }}"
    >
        Semana
    </a>

    <a
        href="{{ route('scheduling.admin.calendar', ['view' => 'month']) }}"
        class="{{ request()->routeIs('scheduling.admin.calendar') && request('view') === 'month' ? 'is-active' : '' }}"
    >
        Mês
    </a>

    <a
        href="{{ route('scheduling.admin.event-types') }}"
        class="{{ request()->routeIs('scheduling.admin.event-types') ? 'is-active' : '' }}"
    >
        Tipos e disponibilidade
    </a>
</nav>
