<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'IRCENTER')</title>

    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('ircenter-theme');
                document.documentElement.dataset.theme =
                    savedTheme === 'light' ? 'light' : 'dark';
            } catch (error) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <span>IR</span>
                </div>

                <div>
                    <div class="brand-name">IRCENTER</div>
                    <div class="brand-description">Internet Resource Center</div>
                </div>
            </div>

            <div class="sidebar-section-label">Operação</div>

            <nav class="sidebar-nav" aria-label="Navegação principal">
                <a
                    href="{{ route('dashboard') }}"
                    class="sidebar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="dashboard"/>
                    </span>
                    <span>Dashboard</span>
                </a>

                <a
                    href="{{ route('clients.index') }}"
                    class="sidebar-link {{ request()->routeIs('clients.*') ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="clients"/>
                    </span>
                    <span>Clientes</span>
                </a>

                <a
                    href="{{ route('autonomous-systems.index') }}"
                    class="sidebar-link {{ request()->routeIs('autonomous-systems.*') ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="asn"/>
                    </span>
                    <span>ASNs</span>
                </a>

                <a
                    href="{{ route('prefixes.ipv4') }}"
                    class="sidebar-link {{ request()->routeIs('prefixes.ipv4') || (request()->routeIs('prefixes.*') && request('version') == 4) ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="ipv4"/>
                    </span>
                    <span>Prefixos IPv4</span>
                </a>

                <a
                    href="{{ route('prefixes.ipv6') }}"
                    class="sidebar-link {{ request()->routeIs('prefixes.ipv6') || (request()->routeIs('prefixes.*') && request('version') == 6) ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="ipv6"/>
                    </span>
                    <span>Prefixos IPv6</span>
                </a>
            </nav>

            <div class="sidebar-section-label">Plataforma</div>

            <nav class="sidebar-nav" aria-label="Navegação da plataforma">
                <a
                    href="{{ route('irr-workflows.index') }}"
                    class="sidebar-link {{ request()->routeIs('irr-workflows.*') ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="workflow"/>
                    </span>
                    <span>Assistente IRR</span>
                </a>

                <a
                    href="{{ route('irr-objects.index') }}"
                    class="sidebar-link {{ request()->routeIs('irr-objects.*') ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="registry"/>
                    </span>
                    <span>Objetos IRR</span>
                </a>

                <a
                    href="{{ route('rpki.index') }}"
                    class="sidebar-link {{ request()->routeIs('rpki.*') ? 'is-active' : '' }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="certificate"/>
                    </span>
                    <span>RPKI</span>
                </a>
                @if (auth()->user()->isAdministrator())
                    <a
                        href="{{ route('users.index') }}"
                        class="sidebar-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}"
                    >
                        <span class="sidebar-link-icon">
                            <x-icon name="clients"/>
                        </span>
                        <span>Usuários</span>
                    </a>
                @endif

                @if (auth()->user()->isAdministrator())
                    <a
                        href="{{ route('audit.index') }}"
                        class="sidebar-link {{ request()->routeIs('audit.*') ? 'is-active' : '' }}"
                    >
                        <span class="sidebar-link-icon">
                            <x-icon name="registry"/>
                        </span>
                        <span>Auditoria</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <div class="environment-status">
                    <span class="status-dot"></span>

                    <div>
                        <strong>Plataforma operacional</strong>
                        <span>Serviços principais online</span>
                    </div>
                </div>
            </div>
        </aside>

        <div class="app-content">
            <header class="app-topbar">
                <div class="topbar-search">
                    <x-icon name="search" size="18"/>

                    <input
                        type="search"
                        placeholder="Buscar recursos..."
                        aria-label="Buscar recursos"
                        disabled
                    >

                    <span class="search-shortcut">⌘ K</span>
                </div>

                <div class="topbar-actions">
                    <button
                        id="theme-toggle"
                        class="topbar-icon-button theme-toggle"
                        type="button"
                        aria-label="Alternar tema"
                        title="Alternar tema claro/escuro"
                    >
                        <span class="theme-icon theme-icon-sun">
                            <x-icon name="sun" size="19"/>
                        </span>

                        <span class="theme-icon theme-icon-moon">
                            <x-icon name="moon" size="19"/>
                        </span>
                    </button>

                    <button class="topbar-icon-button" type="button" aria-label="Notificações" disabled>
                        <x-icon name="bell" size="19"/>
                        <span class="notification-indicator"></span>
                    </button>

                    <div class="user-menu">
                        <div class="user-avatar">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </div>

                        <div class="user-details">
                            <strong>{{ auth()->user()->name }}</strong>
                            <span>{{ auth()->user()->roleLabel() }}</span>
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button class="logout-button" type="submit" title="Sair">
                                <x-icon name="logout" size="18"/>
                                <span>Sair</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="page-content">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (() => {
            const button = document.getElementById('theme-toggle');

            if (! button) {
                return;
            }

            button.addEventListener('click', () => {
                const currentTheme =
                    document.documentElement.dataset.theme || 'dark';

                const nextTheme =
                    currentTheme === 'light' ? 'dark' : 'light';

                document.documentElement.dataset.theme = nextTheme;

                try {
                    localStorage.setItem('ircenter-theme', nextTheme);
                } catch (error) {
                    // O tema continua funcionando durante a sessão.
                }
            });
        })();
    </script>
</body>
</html>
