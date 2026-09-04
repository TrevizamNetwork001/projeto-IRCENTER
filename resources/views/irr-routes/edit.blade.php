@extends('layouts.app')

@section('title', 'Editar objeto route — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1 class="table-mono">{{ $route->prefix }}</h1>
            <p>Alterar o objeto exige republicar o texto inteiro — não existe PATCH na API do TC.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-routes.update', $route) }}">
            @method('PUT')

            @include('irr-routes._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('irr-routes.show', $route),
            ])
        </form>
    </section>
@endsection
