@extends('layouts.app')

@section('title', 'Editar ASN — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Atualização cadastral</div>
            <h1>Editar {{ $autonomousSystem->formattedAsn() }}</h1>
            <p>{{ $autonomousSystem->name }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('autonomous-systems.update', $autonomousSystem) }}">
            @method('PUT')

            @include('autonomous-systems._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('autonomous-systems.show', $autonomousSystem),
            ])
        </form>
    </section>
@endsection
