@extends('layouts.app')

@section('title', 'Configurar cobrança recorrente — IRCENTER')

@section('content')
    @include('finance._nav')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Financeiro
            </div>

            <h1>Configurar cobrança recorrente</h1>

            <p>
                Defina item, valor, geração e vencimento mensal do cliente.
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
