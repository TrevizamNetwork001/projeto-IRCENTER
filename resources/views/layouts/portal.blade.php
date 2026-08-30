<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Portal do Cliente | IRCENTER')</title>

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
</head>
<body class="app-body">
    <a class="skip-link" href="#main-content">Ir para o conteúdo principal</a>

    <main id="main-content" class="page-content" tabindex="-1">
        <header class="portal-topbar">
            <div>
                <div class="brand">IRCENTER</div>
                <div class="page-eyebrow">Portal do cliente</div>
            </div>

            @auth('client')
                <div class="portal-topbar-actions">
                    <span class="table-secondary-text">
                        {{ auth('client')->user()->name ?: auth('client')->user()->email }}
                    </span>

                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf

                        <button class="logout-button" type="submit">
                            <x-icon name="logout" size="16"/>
                            <span>Sair</span>
                        </button>
                    </form>
                </div>
            @endauth
        </header>

        @yield('content')
    </main>
</body>
</html>
