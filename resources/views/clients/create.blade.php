@extends('layouts.app')

@section('title', 'Novo cliente — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Cadastro</div>
            <h1>Novo cliente</h1>
            <p>Cadastre uma organização para associar ASNs e prefixos.</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('clients.store') }}">
            @include('clients._form', [
                'submitLabel' => 'Cadastrar cliente',
                'cancelUrl' => route('clients.index'),
            ])
        </form>
    </section>
@endsection
