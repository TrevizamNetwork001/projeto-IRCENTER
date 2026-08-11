<nav class="finance-nav" aria-label="Navegação financeira">
    <a class="{{ request()->routeIs('finance.dashboard') ? 'is-active' : '' }}" href="{{ route('finance.dashboard') }}">Visão geral</a>
    <a class="{{ request()->routeIs('finance.contracts.*') ? 'is-active' : '' }}" href="{{ route('finance.contracts.index') }}">Contratos</a>
    <a class="{{ request()->routeIs('finance.invoices.*') || request()->routeIs('finance.charges.*') ? 'is-active' : '' }}" href="{{ route('finance.invoices.index') }}">Faturas / Cobranças</a>
    <a class="{{ request()->routeIs('finance.items.*') ? 'is-active' : '' }}" href="{{ route('finance.items.index') }}">Itens de cobrança</a>
</nav>
