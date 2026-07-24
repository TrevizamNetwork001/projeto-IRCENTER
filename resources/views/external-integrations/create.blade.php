@extends('layouts.app')

@section('title', 'Nova integração — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Integrações externas
            </div>

            <h1>Nova integração</h1>

            <p>
                Cadastre um endpoint externo com acesso e testes controlados.
            </p>
        </div>
    </section>

    <section class="panel form-panel">
        <form
            method="POST"
            action="{{ route('external-integrations.store') }}"
        >
            @include('external-integrations._form')
        </form>
    </section>
@endsection
