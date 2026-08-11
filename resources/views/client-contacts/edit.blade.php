@extends('layouts.app')

@section('title', 'Editar contato — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Contato estruturado</div>
            <h1>Editar contato</h1>
            <p>{{ $client->displayName() }}</p>
        </div>
    </section>

    <section class="panel form-panel form-panel-compact">
        <form method="POST" action="{{ route('clients.contacts.update', [$client, $contact]) }}">
            @method('PUT')
            @include('client-contacts._form', [
                'submitLabel' => 'Salvar contato',
                'cancelUrl' => route('clients.show', $client),
            ])
        </form>
    </section>
@endsection
