<x-layouts.auth title="Portal do Cliente — IRCENTER">
    <main id="auth-main" class="auth-shell" tabindex="-1">
        <x-auth.login-hero/>

        <section class="auth-login-panel">
            <button
                id="auth-theme-toggle"
                class="auth-theme-toggle"
                type="button"
                aria-label="Alternar tema claro e escuro"
                title="Alternar tema"
            >
                <span class="theme-icon theme-icon-sun">
                    <x-icon name="sun" size="18"/>
                </span>

                <span class="theme-icon theme-icon-moon">
                    <x-icon name="moon" size="18"/>
                </span>
            </button>

            <div class="auth-login-card">
                <header class="auth-login-header">
                    <span class="page-eyebrow auth-login-eyebrow">
                        <x-icon name="shield" size="14"/>
                        Portal do cliente
                    </span>

                    <h2>Acessar minha conta</h2>

                    <p>
                        Entre com o e-mail e a senha fornecidos pela sua
                        equipe de atendimento.
                    </p>
                </header>

                @if ($errors->any())
                    <div class="alert-error" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('portal.login.store') }}">
                    @csrf

                    <div class="auth-field">
                        <label for="email">E-mail</label>

                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon">
                                <x-icon name="mail" size="18"/>
                            </span>

                            <input
                                id="email"
                                class="auth-input"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="seu@email.com"
                                autocomplete="email"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="password">Senha</label>

                        <div class="auth-input-wrapper">
                            <span class="auth-input-icon">
                                <x-icon name="lock" size="18"/>
                            </span>

                            <input
                                id="password"
                                class="auth-input"
                                name="password"
                                type="password"
                                placeholder="Sua senha"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                id="password-toggle"
                                class="auth-password-toggle"
                                type="button"
                                aria-label="Mostrar senha"
                            >
                                <span data-eye-open>
                                    <x-icon name="eye" size="18"/>
                                </span>

                                <span data-eye-closed hidden>
                                    <x-icon name="eye-off" size="18"/>
                                </span>
                            </button>
                        </div>
                    </div>

                    <button class="primary-button auth-submit" type="submit">
                        <span>Entrar</span>
                        <x-icon name="chevron-right" size="18"/>
                    </button>
                </form>
            </div>

            <p class="auth-page-footer">© {{ now()->year }} IRCENTER</p>
        </section>
    </main>
</x-layouts.auth>
