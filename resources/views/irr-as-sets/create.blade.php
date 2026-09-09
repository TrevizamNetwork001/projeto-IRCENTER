@extends('layouts.app')

@section('title', 'Novo AS-set — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>Novo AS-set</h1>
            <p>Cadastre um AS-set para publicar na base do TC.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-as-sets.store') }}">
            @include('irr-as-sets._form', [
                'submitLabel' => 'Cadastrar AS-set',
                'cancelUrl' => route('irr-as-sets.index'),
            ])
        </form>
    </section>
@endsection
