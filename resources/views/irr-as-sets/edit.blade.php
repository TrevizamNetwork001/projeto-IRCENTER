@extends('layouts.app')

@section('title', 'Editar AS-set — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1 class="table-mono">{{ $asSet->name }}</h1>
            <p>Alterar o objeto exige republicar o texto inteiro — não existe PATCH na API do TC.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-as-sets.update', $asSet) }}">
            @method('PUT')

            @include('irr-as-sets._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('irr-as-sets.show', $asSet),
            ])
        </form>
    </section>
@endsection
