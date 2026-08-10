@props([
    'title' => 'IRCENTER',
])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>{{ $title }}</title>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            try {
                const savedTheme =
                    localStorage.getItem('ircenter-theme');

                document.documentElement.dataset.theme =
                    savedTheme === 'light' ? 'light' : 'dark';
            } catch (error) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>

    <link
        rel="stylesheet"
        href="{{ asset('assets/app.css') }}"
    >
</head>

<body class="auth-body">
    {{ $slot }}

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const themeButton =
                document.getElementById('auth-theme-toggle');

            themeButton?.addEventListener('click', () => {
                const currentTheme =
                    document.documentElement.dataset.theme || 'dark';

                const nextTheme =
                    currentTheme === 'light' ? 'dark' : 'light';

                document.documentElement.dataset.theme = nextTheme;

                try {
                    localStorage.setItem(
                        'ircenter-theme',
                        nextTheme
                    );
                } catch (error) {
                    // O tema permanece ativo durante esta sessão.
                }
            });

            const passwordInput =
                document.getElementById('password');

            const passwordToggle =
                document.getElementById('password-toggle');

            passwordToggle?.addEventListener('click', () => {
                if (! passwordInput) {
                    return;
                }

                const isPassword =
                    passwordInput.type === 'password';

                passwordInput.type =
                    isPassword ? 'text' : 'password';

                passwordToggle.setAttribute(
                    'aria-label',
                    isPassword
                        ? 'Ocultar senha'
                        : 'Mostrar senha'
                );

                passwordToggle
                    .querySelector('[data-eye-open]')
                    ?.toggleAttribute('hidden', isPassword);

                passwordToggle
                    .querySelector('[data-eye-closed]')
                    ?.toggleAttribute('hidden', ! isPassword);
            });
        })();
    </script>
</body>
</html>
