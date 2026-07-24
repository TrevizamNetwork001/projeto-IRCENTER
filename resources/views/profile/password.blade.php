@extends('layouts.app')

@section('title', 'Alterar senha | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">Segurança da conta</span>
            <h1>Alterar minha senha</h1>
            <p>
                Confirme sua senha atual e defina uma nova senha de acesso.
            </p>
        </div>
    </section>

    <section class="panel profile-password-panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Credenciais</span>
                <h2>Nova senha</h2>
            </div>
        </header>

        @if ($errors->any())
            <div class="alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('profile.password.update') }}"
        >
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field-group field-span-2">
                    <label for="current_password">
                        Senha atual <span>*</span>
                    </label>

                    <input
                        id="current_password"
                        class="form-control"
                        name="current_password"
                        type="password"
                        required
                        autofocus
                        autocomplete="current-password"
                    >
                </div>

                <div class="field-group">
                    <label for="password">
                        Nova senha <span>*</span>
                    </label>

                    <input
                        id="password"
                        class="form-control"
                        name="password"
                        type="password"
                        minlength="10"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <div class="field-group">
                    <label for="password_confirmation">
                        Confirmar nova senha <span>*</span>
                    </label>

                    <input
                        id="password_confirmation"
                        class="form-control"
                        name="password_confirmation"
                        type="password"
                        minlength="10"
                        required
                        autocomplete="new-password"
                    >
                </div>
            </div>

            <div class="password-requirements">
                Use no mínimo 10 caracteres, incluindo letras e números.
            </div>

            <div class="form-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('profile.edit') }}"
                >
                    Cancelar
                </a>

                <button class="button button-primary" type="submit">
                    Alterar senha
                </button>
            </div>
        </form>
    </section>
@endsection
