@extends('layouts.app')

@section('title', 'Editar usuário | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Administração de usuários
            </div>

            <h1>{{ $managedUser->name }}</h1>

            <p>{{ $managedUser->email }}</p>
        </div>

        <div class="page-actions">
            <a class="button button-secondary" href="{{ route('users.index') }}">
                Voltar à lista
            </a>

            <form
                method="POST"
                action="{{ route('users.toggle-active', $managedUser) }}"
            >
                @csrf
                @method('PATCH')

                <button class="button button-secondary" type="submit">
                    {{ $managedUser->active ? 'Bloquear' : 'Ativar' }}
                </button>
            </form>
        </div>
    </section>

    <div class="split-layout">
        <div class="split-layout-main">
            <section class="panel">
                <form
                    method="POST"
                    action="{{ route('users.update', $managedUser) }}"
                >
                    @include('users._form')
                </form>
            </section>

            <section class="panel">
                <header class="panel-header">
                    <div>
                        <span class="panel-eyebrow">Segurança</span>
                        <h2>Redefinir senha</h2>
                    </div>
                </header>

                <form
                    method="POST"
                    action="{{ route(
                        'users.reset-password',
                        $managedUser
                    ) }}"
                >
                    @csrf
                    @method('PATCH')

                    <div class="form-grid">
                        <div class="field-group">
                            <label for="reset_password">
                                Nova senha <span>*</span>
                            </label>

                            <input
                                id="reset_password"
                                class="form-control"
                                name="password"
                                type="password"
                                minlength="10"
                                required
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="field-group">
                            <label for="reset_password_confirmation">
                                Confirmar senha <span>*</span>
                            </label>

                            <input
                                id="reset_password_confirmation"
                                class="form-control"
                                name="password_confirmation"
                                type="password"
                                minlength="10"
                                required
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="field-group field-checkbox-group">
                            <label class="checkbox-label">
                                <input
                                    type="hidden"
                                    name="must_change_password"
                                    value="0"
                                >

                                <input
                                    name="must_change_password"
                                    type="checkbox"
                                    value="1"
                                    checked
                                >

                                <span>
                                    <strong>Exigir troca no próximo acesso</strong>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">
                            Redefinir senha
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <aside class="tip-card">
            <span class="tip-card-icon">
                <x-icon name="shield" size="17"/>
            </span>

            <div>
                <h3>Dica de segurança</h3>
                <p>
                    Mantenha o princípio do privilégio mínimo: atribua o
                    perfil "Operador" ou "Somente leitura" sempre que o
                    acesso de administrador não for necessário.
                </p>
            </div>
        </aside>
    </div>
@endsection
