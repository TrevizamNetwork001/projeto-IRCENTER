<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Definir nova senha — IRCENTER</title>

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

<body>
    <main class="login-page">
        <section class="login-card password-change-card">
            <div class="brand">IRCENTER</div>

            <h1>Defina uma nova senha</h1>

            <p class="subtitle">
                A troca de senha é obrigatória antes de acessar
                a plataforma.
            </p>

            @if (session('warning'))
                <div class="alert-error">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('password.change.update') }}"
            >
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="current_password">
                        Senha atual
                    </label>

                    <input
                        id="current_password"
                        class="form-control"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        Nova senha
                    </label>

                    <input
                        id="password"
                        class="form-control"
                        name="password"
                        type="password"
                        minlength="10"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password_confirmation">
                        Confirmar nova senha
                    </label>

                    <input
                        id="password_confirmation"
                        class="form-control"
                        name="password_confirmation"
                        type="password"
                        minlength="10"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="password-requirements">
                    Use no mínimo 10 caracteres, incluindo letras
                    e números.
                </div>

                <button class="primary-button" type="submit">
                    Alterar senha e continuar
                </button>
            </form>

            <form
                class="password-change-logout"
                method="POST"
                action="{{ route('logout') }}"
            >
                @csrf

                <button
                    class="button button-secondary"
                    type="submit"
                >
                    Sair
                </button>
            </form>
        </section>
    </main>
</body>
</html>
