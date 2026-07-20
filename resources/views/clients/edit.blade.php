@extends('layouts.app')

@section('title', 'Editar cliente — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Atualização cadastral</div>
            <h1>Editar cliente</h1>
            <p>{{ $client->displayName() }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('clients.update', $client) }}">
            @method('PUT')

            @include('clients._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('clients.show', $client),
            ])
        </form>
    </section>
@endsection
