<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('code') — IRCENTER</title>

    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="error-page">
    <main class="error-page-shell">
        <div class="error-page-brand">
            <span class="brand-mark">IR</span>
            <strong>IRCENTER</strong>
        </div>

        <section class="error-page-card">
            <span class="error-page-code">@yield('code')</span>

            <h1>@yield('title')</h1>

            <p>@yield('message')</p>

            <div class="error-page-actions">
                <a
                    class="button button-primary"
                    href="{{ auth()->check()
                        ? route('dashboard')
                        : route('login') }}"
                >
                    {{ auth()->check()
                        ? 'Voltar ao dashboard'
                        : 'Ir para o login' }}
                </a>

                <button
                    class="button button-secondary"
                    type="button"
                    onclick="history.back()"
                >
                    Voltar
                </button>
            </div>
        </section>
    </main>
</body>
</html>
