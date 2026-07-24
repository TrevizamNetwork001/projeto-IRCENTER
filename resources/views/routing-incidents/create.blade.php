@extends('layouts.app')

@section('title', 'Novo incidente — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Roteamento e segurança
            </div>

            <h1>Novo incidente</h1>

            <p>
                Registre a ocorrência, seu impacto e os recursos envolvidos.
            </p>
        </div>
    </section>

    <section class="panel form-panel">
        <form
            method="POST"
            action="{{ route('routing-incidents.store') }}"
        >
            @include('routing-incidents._form')
        </form>
    </section>
@endsection
