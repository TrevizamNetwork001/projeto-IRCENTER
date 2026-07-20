<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — IRCENTER</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
    <main class="login-page">
        <section class="login-card">
            <div class="brand">IRCENTER</div>
            <h1>Acesso ao painel</h1>
            <p class="subtitle">Internet Resource Center</p>

            @if ($errors->any())
                <div class="alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input
                        id="email"
                        class="form-control"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input
                        id="password"
                        class="form-control"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <label class="remember">
                    <input name="remember" type="checkbox" value="1">
                    Manter sessão ativa
                </label>

                <button class="primary-button" type="submit">
                    Entrar
                </button>
            </form>
        </section>
    </main>
</body>
</html>
