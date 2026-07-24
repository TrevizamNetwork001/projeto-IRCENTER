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

                    <div class="notification-menu">
                        <button
                            id="notification-menu-toggle"
                            class="topbar-icon-button"
                            type="button"
                            aria-label="Notificações"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="notification-menu-dropdown"
                        >
                            <x-icon name="bell" size="19"/>

                            @if ($topbarUnreadNotificationCount > 0)
                                <span class="notification-indicator">
                                    {{ $topbarUnreadNotificationCount > 9
                                        ? '9+'
                                        : $topbarUnreadNotificationCount }}
                                </span>
                            @endif
                        </button>

                        <div
                            id="notification-menu-dropdown"
                            class="notification-menu-dropdown"
                            role="menu"
                            hidden
                        >
                            <header class="notification-menu-header">
                                <div>
                                    <strong>Notificações</strong>
                                    <span>
                                        {{ $topbarUnreadNotificationCount }}
                                        não lida(s)
                                    </span>
                                </div>

                                <a href="{{ route('notifications.index') }}">
                                    Ver todas
                                </a>
                            </header>

                            @if ($topbarNotifications->isEmpty())
                                <div class="notification-menu-empty">
                                    <x-icon name="bell" size="20"/>

                                    <div>
                                        <strong>Nenhuma pendência</strong>
                                        <span>
                                            O ambiente está consistente.
                                        </span>
                                    </div>
                                </div>
                            @else
                                <div class="notification-menu-list">
                                    @foreach (
                                        $topbarNotifications as $notification
                                    )
                                        <a
                                            class="notification-menu-item
                                                {{ $notification->isUnread()
                                                    ? 'is-unread'
                                                    : '' }}"
                                            href="{{ $notification->action_url
                                                ?: route(
                                                    'notifications.index'
                                                ) }}"
                                            role="menuitem"
                                        >
                                            <span
                                                class="notification-priority-dot
                                                    is-{{
                                                        $notification->priority
                                                    }}"
                                            ></span>

                                            <span
                                                class="notification-menu-content"
                                            >
                                                <strong>
                                                    {{ $notification->title }}
                                                </strong>

                                                <span>
                                                    {{ $notification->message }}
                                                </span>
                                            </span>

                                            <time
                                                datetime="{{
                                                    $notification->updated_at
                                                }}"
                                            >
                                                {{ $notification->updated_at
                                                    ?->diffForHumans() }}
                                            </time>
                                        </a>
                                    @endforeach
                                </div>

                                <footer class="notification-menu-footer">
                                    <a
                                        href="{{
                                            route('notifications.index')
                                        }}"
                                    >
                                        Abrir Central de Notificações
                                    </a>
                                </footer>
                            @endif
                        </div>
                    </div>

                    <div class="user-menu account-menu">
                        <button
                            id="account-menu-toggle"
                            class="user-profile-link account-menu-toggle"
                            type="button"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="account-menu-dropdown"
                        >
                            <x-user-avatar :user="auth()->user()"/>

                            <div class="user-details">
                                <strong>{{ auth()->user()->name }}</strong>
                                <span>{{ auth()->user()->roleLabel() }}</span>
                            </div>

                            <span
                                class="account-menu-chevron"
                                aria-hidden="true"
                            >
                                ▾
                            </span>
                        </button>

                        <div
                            id="account-menu-dropdown"
                            class="account-menu-dropdown"
                            role="menu"
                            hidden
                        >
                            <div class="account-menu-header">
                                <strong>{{ auth()->user()->name }}</strong>
                                <span>{{ auth()->user()->roleLabel() }}</span>
                            </div>

                            <a
                                class="account-menu-item"
                                href="{{ route('profile.edit') }}"
                                role="menuitem"
                            >
                                Meu perfil
                            </a>

                            <a
                                class="account-menu-item"
                                href="{{ route('profile.password.edit') }}"
                                role="menuitem"
                            >
                                Alterar minha senha
                            </a>

                            @if (auth()->user()->isAdministrator())
                                <a
                                    class="account-menu-item"
                                    href="{{ route('users.index') }}"
                                    role="menuitem"
                                >
                                    Gerenciar usuários
                                </a>
                            @endif

                            <div class="account-menu-divider"></div>

                            <form
                                method="POST"
                                action="{{ route('logout') }}"
                            >
                                @csrf

                                <button
                                    class="account-menu-item account-menu-logout"
                                    type="submit"
                                    role="menuitem"
                                >
                                    <x-icon name="logout" size="16"/>
                                    <span>Sair</span>
                                </button>
                            </form>
                        </div>
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

    <script>
        (() => {
            const toggle = document.getElementById(
                'account-menu-toggle'
            );

            const dropdown = document.getElementById(
                'account-menu-dropdown'
            );

            const closeMenu = () => {
                if (! toggle || ! dropdown) {
                    return;
                }

                dropdown.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle?.addEventListener('click', event => {
                event.stopPropagation();

                const willOpen = dropdown.hidden;

                dropdown.hidden = ! willOpen;
                toggle.setAttribute(
                    'aria-expanded',
                    willOpen ? 'true' : 'false'
                );
            });

            dropdown?.addEventListener('click', event => {
                event.stopPropagation();
            });

            document.addEventListener('click', closeMenu);

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    closeMenu();
                    toggle?.focus();
                }
            });

            document
                .querySelectorAll('[data-auto-dismiss]')
                .forEach(alert => {
                    const delay = Number(
                        alert.dataset.autoDismiss || 5000
                    );

                    window.setTimeout(() => {
                        alert.classList.add('is-hiding');

                        window.setTimeout(() => {
                            alert.remove();
                        }, 300);
                    }, delay);
                });
        })();
    </script>
    <script>
        (() => {
            const toggle = document.getElementById(
                'notification-menu-toggle'
            );

            const dropdown = document.getElementById(
                'notification-menu-dropdown'
            );

            const closeMenu = () => {
                if (! toggle || ! dropdown) {
                    return;
                }

                dropdown.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle?.addEventListener('click', event => {
                event.stopPropagation();

                const willOpen = dropdown.hidden;

                dropdown.hidden = ! willOpen;
                toggle.setAttribute(
                    'aria-expanded',
                    willOpen ? 'true' : 'false'
                );

                if (willOpen) {
                    const accountDropdown = document.getElementById(
                        'account-menu-dropdown'
                    );

                    const accountToggle = document.getElementById(
                        'account-menu-toggle'
                    );

                    if (accountDropdown) {
                        accountDropdown.hidden = true;
                    }

                    accountToggle?.setAttribute(
                        'aria-expanded',
                        'false'
                    );
                }
            });

            dropdown?.addEventListener('click', event => {
                event.stopPropagation();
            });

            document.addEventListener('click', closeMenu);

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    closeMenu();
                    toggle?.focus();
                }
            });
        })();
    </script>

</body>
</html>
