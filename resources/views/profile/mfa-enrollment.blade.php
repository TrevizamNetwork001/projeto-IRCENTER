@extends('layouts.app')

@section('title', 'Ativar MFA | IRCENTER')

@section('content')
    <section class="page-heading"><div><span class="page-eyebrow">Segurança</span><h1>Ativar MFA</h1></div></section>
    <section class="panel">
        <p>Adicione manualmente esta URI em um autenticador TOTP. Ela será exibida apenas durante o enrollment.</p>
        <code style="overflow-wrap:anywhere">{{ $enrollment['uri'] }}</code>
        <p>Segredo manual: <code>{{ $enrollment['secret'] }}</code></p>
        @if ($errors->any()) <div class="alert-error">{{ $errors->first() }}</div> @endif
        <form method="POST" action="{{ route('mfa.confirm') }}">
            @csrf
            <div class="field-group"><label for="code">Código de 6 dígitos</label>
                <input id="code" class="form-control" name="code" required inputmode="numeric" autocomplete="one-time-code">
            </div>
            <button class="button button-primary" type="submit">Confirmar e ativar</button>
        </form>
    </section>
@endsection
