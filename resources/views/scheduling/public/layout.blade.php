<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="index,follow">
    <title>@yield('title', 'Agenda — IRCENTER')</title>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            try {
                const savedTheme = localStorage.getItem('ircenter-theme');
                document.documentElement.dataset.theme = savedTheme === 'light' ? 'light' : 'dark';
            } catch (error) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/scheduling.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/scheduling-final.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/scheduling-public-ux.css') }}">
</head>
<body class="scheduling-public">
    <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
    <div class="scheduling-public-topology" aria-hidden="true"></div>
    <header class="scheduling-public-brand" aria-label="IRCENTER">
        <div class="scheduling-public-brand-identity">
            <img class="scheduling-public-mark" src="{{ asset('assets/logo-trevizam-icon.png') }}" alt="Trevizam Networks">
            <div>
                <strong>IRCENTER</strong>
                <small>Internet Resource Center</small>
            </div>
        </div>
        <div class="scheduling-public-header-actions">
            <span class="scheduling-public-locale" aria-label="Idioma: Português do Brasil"><x-icon name="globe" size="14"/> PT-BR</span>
            <button
                id="theme-toggle"
                class="topbar-icon-button theme-toggle scheduling-public-theme-toggle"
                type="button"
                aria-label="Alternar tema"
                title="Alternar tema claro/escuro"
            >
                <span class="theme-icon theme-icon-sun"><x-icon name="sun" size="18"/></span>
                <span class="theme-icon theme-icon-moon"><x-icon name="moon" size="18"/></span>
            </button>
        </div>
    </header>
    <main id="main-content" class="scheduling-public-main" tabindex="-1">
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @yield('content')
    </main>
    @stack('scripts')

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const button = document.getElementById('theme-toggle');
            if (!button) return;
            button.addEventListener('click', () => {
                const current = document.documentElement.dataset.theme || 'dark';
                const next = current === 'light' ? 'dark' : 'light';
                document.documentElement.dataset.theme = next;
                try { localStorage.setItem('ircenter-theme', next); } catch (error) {}
            });
        })();
    </script>
</body>
</html>
