@extends('layouts.app')

@section('title', 'Editar prefixo — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Atualização cadastral</div>

            <h1>Editar prefixo</h1>

            <p class="table-mono">{{ $prefix->prefix }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('prefixes.update', $prefix) }}">
            @method('PUT')

            @include('prefixes._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('prefixes.show', $prefix),
            ])
        </form>
    </section>
@endsection
