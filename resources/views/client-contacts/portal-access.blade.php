@extends('layouts.app')

@section('title', 'Acesso ao portal | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Administração de acessos
            </div>

            <h1>Acesso ao portal — {{ $contact->name ?: $contact->email }}</h1>

            <p>{{ $client->displayName() }} · {{ $contact->typeLabel() }}</p>
        </div>

        <a class="button button-secondary" href="{{ route('clients.show', $client) }}">
            Voltar ao cliente
        </a>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Portal do cliente</span>
                <h2>{{ $contact->password ? 'Redefinir senha' : 'Liberar acesso' }}</h2>
            </div>
        </header>

        <p class="field-hint">
            Este contato poderá acessar
            <strong>{{ url('/portal/login') }}</strong>
            com o e-mail <strong>{{ $contact->email ?: '—' }}</strong>
            e a senha definida abaixo.
        </p>

        @unless ($contact->email)
            <div class="alert-error" role="alert">
                Cadastre um e-mail para este contato antes de liberar o acesso ao portal.
            </div>
        @endunless

        <form
            method="POST"
            action="{{ route('clients.contacts.portal-access.store', [$client, $contact]) }}"
        >
            @csrf

            <div class="form-grid">
                <div class="field-group">
                    <label for="password">
                        {{ $contact->password ? 'Nova senha' : 'Senha' }} <span>*</span>
                    </label>

                    <input
                        id="password"
                        class="form-control"
                        name="password"
                        type="password"
                        minlength="10"
                        required
                        autocomplete="new-password"
                        @disabled(! $contact->email)
                    >

                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="password_confirmation">
                        Confirmar senha <span>*</span>
                    </label>

                    <input
                        id="password_confirmation"
                        class="form-control"
                        name="password_confirmation"
                        type="password"
                        minlength="10"
                        required
                        autocomplete="new-password"
                        @disabled(! $contact->email)
                    >
                </div>

                <div class="field-group field-checkbox-group">
                    <label class="checkbox-label">
                        <input type="hidden" name="must_change_password" value="0">

                        <input
                            name="must_change_password"
                            type="checkbox"
                            value="1"
                            checked
                        >

                        <span>
                            <strong>Exigir troca no primeiro acesso</strong>
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button
                    class="button button-primary"
                    type="submit"
                    @disabled(! $contact->email)
                >
                    {{ $contact->password ? 'Redefinir senha' : 'Liberar acesso' }}
                </button>
            </div>
        </form>

        @if ($contact->password)
            <form
                method="POST"
                action="{{ route('clients.contacts.portal-access.destroy', [$client, $contact]) }}"
                onsubmit="return confirm('Revogar o acesso deste contato ao portal?');"
            >
                @csrf
                @method('DELETE')

                <div class="form-actions">
                    <button class="button button-secondary" type="submit">
                        Revogar acesso ao portal
                    </button>
                </div>
            </form>
        @endif
    </section>
@endsection
