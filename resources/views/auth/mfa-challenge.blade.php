<x-layouts.auth title="MFA — IRCENTER">
    <main id="auth-main" class="auth-shell" tabindex="-1">
        <section class="auth-card">
            <div class="auth-card__body">
                <span class="page-eyebrow">Segundo fator</span>
                <h1>Confirme seu acesso</h1>
                <p>Informe o código do autenticador ou um código de recuperação.</p>

                @if ($errors->any())
                    <div class="alert-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('mfa.challenge.store') }}">
                    @csrf
                    <div class="field-group">
                        <label for="code">Código</label>
                        <input id="code" class="form-control" name="code" type="text"
                            required autofocus autocomplete="one-time-code" inputmode="numeric">
                    </div>
                    <div class="form-actions">
                        <button class="button button-primary" type="submit">Validar código</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</x-layouts.auth>
