@extends('layouts.app')

@section('title', 'Novo objeto route — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>Novo objeto route</h1>
            <p>Cadastre um route/route6 para publicar na base do TC.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-routes.store') }}">
            @include('irr-routes._form', [
                'submitLabel' => 'Cadastrar objeto',
                'cancelUrl' => route('irr-routes.index'),
            ])
        </form>
    </section>
@endsection
