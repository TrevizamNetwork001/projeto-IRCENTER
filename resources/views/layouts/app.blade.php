<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'IRCENTER')</title>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
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
    <link rel="stylesheet" href="{{ asset('assets/scheduling.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/scheduling-final.css') }}">
</head>
<body class="app-body">
    <a class="skip-link" href="#main-content">Ir para o conteúdo principal</a>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <img
                    class="brand-logo"
                    src="{{ asset('assets/logo-trevizam-icon.png') }}"
                    alt="Trevizam Networks"
                >

                <div>
                    <div class="brand-name">IRCENTER</div>
                    <div class="brand-description">Internet Resource Center</div>
                </div>

                <button
                    id="mobile-menu-toggle"
                    class="mobile-menu-toggle"
                    type="button"
                    aria-label="Abrir menu principal"
                    aria-controls="sidebar-navigation"
                    aria-expanded="false"
                >
                    <span aria-hidden="true">☰</span>
                </button>
            </div>

            <div id="sidebar-navigation">
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
                    href="{{ route('routing-incidents.index') }}"
                    class="sidebar-link {{
                        request()->routeIs('routing-incidents.*')
                            ? 'is-active'
                            : ''
                    }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="incident"/>
                    </span>
                    <span>Incidentes</span>
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

                @if (config('scheduling.enabled'))
                    <a href="{{ route('scheduling.admin.index') }}" class="sidebar-link {{ request()->routeIs('scheduling.admin.*') ? 'is-active' : '' }}">
                        <span class="sidebar-link-icon" aria-hidden="true">▦</span><span>Agenda</span>
                    </a>
                @endif

                <a
                    href="{{ route('finance.dashboard') }}"
                    class="sidebar-link {{
                        request()->routeIs('finance.*')
                            ? 'is-active'
                            : ''
                    }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="report"/>
                    </span>
                    <span>Financeiro</span>
                </a>

                @if (config('finance_fiscal.fiscal.enabled', false))
                    <a href="{{ route('fiscal.dashboard') }}" class="sidebar-link {{ request()->routeIs('fiscal.*') ? 'is-active' : '' }}">
                        <span class="sidebar-link-icon"><x-icon name="report"/></span><span>Fiscal</span>
                    </a>
                @endif

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
                    href="{{ route('reports.index') }}"
                    class="sidebar-link {{
                        request()->routeIs('reports.*')
                            ? 'is-active'
                            : ''
                    }}"
                >
                    <span class="sidebar-link-icon">
                        <x-icon name="report"/>
                    </span>
                    <span>Relatórios</span>
                </a>

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
                        href="{{ route('external-integrations.index') }}"
                        class="sidebar-link {{
                            request()->routeIs('external-integrations.*')
                                ? 'is-active'
                                : ''
                        }}"
                    >
                        <span class="sidebar-link-icon">
                            <x-icon name="integration"/>
                        </span>
                        <span>Integrações</span>
                    </a>
                @endif

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
                        href="{{ route('system-diagnostic.index') }}"
                        class="sidebar-link {{
                            request()->routeIs('system-diagnostic.*')
                                ? 'is-active'
                                : ''
                        }}"
                    >
                        <span class="sidebar-link-icon">
                            <x-icon name="activity"/>
                        </span>
                        <span>Diagnóstico</span>
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
            </div>
        </aside>

        <div class="app-content">
            <header class="app-topbar">
                <form
                    class="topbar-search"
                    method="GET"
                    action="{{ route('search.index') }}"
                    role="search"
                >
                    <x-icon name="search" size="18"/>

                    <input
                        id="global-search-input"
                        name="q"
                        type="search"
                        role="combobox"
                        value="{{ request()->routeIs('search.*') ? request('q') : '' }}"
                        placeholder="Buscar recursos..."
                        aria-label="Buscar recursos"
                        aria-autocomplete="list"
                        aria-controls="global-search-suggestions"
                        aria-expanded="false"
                        maxlength="100"
                        autocomplete="off"
                        data-suggestions-url="{{ route('search.suggestions') }}"
                    >

                    <span class="search-shortcut" aria-hidden="true">⌘ K</span>

                    <div
                        id="global-search-suggestions"
                        class="global-search-suggestions"
                        role="listbox"
                        hidden
                    ></div>
                </form>

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

            <main id="main-content" class="page-content" tabindex="-1">
                @yield('content')
            </main>
        </div>
    </div>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const input = document.getElementById('global-search-input');
            const form = input?.closest('form');
            const dropdown = document.getElementById(
                'global-search-suggestions'
            );
            let timer;
            let controller;
            let activeIndex = -1;

            const closeSuggestions = () => {
                if (! input || ! dropdown) return;
                dropdown.hidden = true;
                dropdown.replaceChildren();
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
                activeIndex = -1;
            };

            const selectSuggestion = index => {
                const items = [...dropdown.querySelectorAll('[role="option"]')];
                activeIndex = Math.max(0, Math.min(index, items.length - 1));
                items.forEach((item, itemIndex) => {
                    const selected = itemIndex === activeIndex;
                    item.classList.toggle('is-active', selected);
                    item.setAttribute('aria-selected', selected ? 'true' : 'false');
                });
                const active = items[activeIndex];
                if (active) {
                    input.setAttribute('aria-activedescendant', active.id);
                    active.scrollIntoView({ block: 'nearest' });
                }
            };

            const renderSuggestions = suggestions => {
                dropdown.replaceChildren();

                suggestions.forEach((suggestion, index) => {
                    const link = document.createElement('a');
                    const heading = document.createElement('span');
                    const title = document.createElement('strong');
                    const type = document.createElement('small');
                    const detail = document.createElement('span');

                    link.id = `global-search-option-${index}`;
                    link.className = 'global-search-suggestion';
                    link.href = suggestion.url;
                    link.role = 'option';
                    link.setAttribute('aria-selected', 'false');
                    title.textContent = suggestion.title;
                    type.textContent = suggestion.type;
                    detail.textContent = suggestion.detail || 'Abrir recurso';
                    heading.append(title, type);
                    link.append(heading, detail);
                    dropdown.append(link);
                });

                dropdown.hidden = suggestions.length === 0;
                input.setAttribute(
                    'aria-expanded',
                    suggestions.length === 0 ? 'false' : 'true'
                );
                activeIndex = -1;
            };

            input?.addEventListener('input', () => {
                window.clearTimeout(timer);
                controller?.abort();
                const query = input.value.trim();

                if (query.length < 2) {
                    closeSuggestions();
                    return;
                }

                timer = window.setTimeout(async () => {
                    controller = new AbortController();

                    try {
                        const url = new URL(input.dataset.suggestionsUrl);
                        url.searchParams.set('q', query);
                        const response = await fetch(url, {
                            headers: { Accept: 'application/json' },
                            signal: controller.signal,
                        });

                        if (! response.ok) throw new Error('search_failed');
                        const data = await response.json();
                        renderSuggestions(data.suggestions || []);
                    } catch (error) {
                        if (error.name !== 'AbortError') closeSuggestions();
                    }
                }, 250);
            });

            input?.addEventListener('keydown', event => {
                const items = [...dropdown.querySelectorAll('[role="option"]')];

                if (event.key === 'ArrowDown' && items.length) {
                    event.preventDefault();
                    selectSuggestion(activeIndex + 1);
                } else if (event.key === 'ArrowUp' && items.length) {
                    event.preventDefault();
                    selectSuggestion(activeIndex <= 0 ? items.length - 1 : activeIndex - 1);
                } else if (event.key === 'Enter' && activeIndex >= 0) {
                    event.preventDefault();
                    items[activeIndex]?.click();
                } else if (event.key === 'Escape') {
                    closeSuggestions();
                }
            });

            form?.addEventListener('focusout', event => {
                if (! form.contains(event.relatedTarget)) closeSuggestions();
            });

            document.addEventListener('keydown', event => {
                if ((event.metaKey || event.ctrlKey)
                    && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    input?.focus();
                    input?.select();
                }
            });
        })();
    </script>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const toggle = document.getElementById('mobile-menu-toggle');
            const navigation = document.getElementById('sidebar-navigation');

            toggle?.addEventListener('click', () => {
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                toggle.setAttribute('aria-label', expanded
                    ? 'Abrir menu principal'
                    : 'Fechar menu principal');
                navigation?.classList.toggle('is-open', ! expanded);
            });
        })();
    </script>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
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

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
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
                .querySelectorAll('[data-auto-dismiss], .alert-success')
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
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
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

    @stack('scripts')
</body>
</html>
