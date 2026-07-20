@extends('layouts.app')

@section('title', 'Novo ASN — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Cadastro</div>
            <h1>Novo ASN</h1>
            <p>Vincule um sistema autônomo a um cliente.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('autonomous-systems.store') }}">
            @include('autonomous-systems._form', [
                'submitLabel' => 'Cadastrar ASN',
                'cancelUrl' => route('autonomous-systems.index'),
            ])
        </form>
    </section>
@endsection
