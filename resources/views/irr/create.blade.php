@extends('layouts.app')

@section('title', 'Novo objeto IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry</div>
            <h1>Novo objeto IRR</h1>
            <p>Cadastre um objeto route, route6, aut-num ou maintainer.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-objects.store') }}">
            @include('irr._form', [
                'submitLabel' => 'Cadastrar objeto',
                'cancelUrl' => route('irr-objects.index'),
            ])
        </form>
    </section>
@endsection
