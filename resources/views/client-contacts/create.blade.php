@extends('layouts.app')

@section('title', 'Novo contato — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Contato estruturado</div>
            <h1>Novo contato</h1>
            <p>{{ $client->displayName() }}</p>
        </div>
    </section>

    <section class="panel form-panel form-panel-compact">
        <form method="POST" action="{{ route('clients.contacts.store', $client) }}">
            @include('client-contacts._form', [
                'submitLabel' => 'Cadastrar contato',
                'cancelUrl' => route('clients.show', $client),
            ])
        </form>
    </section>
@endsection
