@extends('layouts.app')

@section('title', 'Segurança da conta | IRCENTER')

@section('content')
    <section class="page-heading">
        <div><span class="page-eyebrow">Perfil</span><h1>Segurança da conta</h1></div>
    </section>

    @if (session('status')) <div class="alert-success">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="alert-error">{{ $errors->first() }}</div> @endif

    <section class="panel">
        <header class="panel-header"><div><span class="panel-eyebrow">Autenticação</span><h2>MFA TOTP</h2></div></header>
        <p>Status: <strong>{{ $mfaEnabled ? 'Ativo' : 'Inativo' }}</strong></p>

        @if (is_array($recoveryCodes))
            <div class="alert-success">
                <strong>Códigos de recuperação — exibidos uma única vez:</strong>
                <ul>@foreach ($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('security.password.confirm') }}">
            @csrf
            <div class="field-group">
                <label for="password">Confirme sua senha para operações críticas</label>
                <input id="password" class="form-control" name="password" type="password"
                    required autocomplete="current-password">
            </div>
            <button class="button button-secondary" type="submit">Confirmar senha</button>
        </form>

        <div class="form-actions">
            @if (! $mfaEnabled)
                <a class="button button-primary" href="{{ route('mfa.enroll') }}">Ativar MFA</a>
            @else
                <form method="POST" action="{{ route('mfa.recovery.regenerate') }}">@csrf
                    <button class="button button-secondary" type="submit">Gerar novos recovery codes</button>
                </form>
                <form method="POST" action="{{ route('mfa.disable') }}">@csrf @method('DELETE')
                    <button class="button button-secondary" type="submit">Desativar MFA</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel">
        <header class="panel-header"><div><span class="panel-eyebrow">Sessões</span><h2>Outros dispositivos</h2></div></header>
        <p>Encerra todas as outras sessões sem exibir seus identificadores.</p>
        <form method="POST" action="{{ route('sessions.others.destroy') }}">@csrf @method('DELETE')
            <button class="button button-secondary" type="submit">Encerrar outras sessões</button>
        </form>
    </section>
@endsection
