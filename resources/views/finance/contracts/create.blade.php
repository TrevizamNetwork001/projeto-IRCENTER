@extends('layouts.app')

@section('title', 'Novo contrato financeiro — IRCENTER')

@section('content')
    @include('finance._nav')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Financeiro
            </div>

            <h1>Novo contrato financeiro</h1>

            <p>
                Cadastre um contrato recorrente para um cliente
                ativo do IRCENTER.
            </p>
        </div>
    </section>

    <section class="panel">
        <form
            method="POST"
            action="{{ route('finance.contracts.store') }}"
        >
            @include('finance.contracts._form')
        </form>
    </section>
@endsection
