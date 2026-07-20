@extends('layouts.app')

@section('title', 'Novo prefixo — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Cadastro</div>

            <h1>Novo prefixo IPv{{ $prefix->ip_version }}</h1>

            <p>
                Cadastre um bloco de endereços e associe-o a um cliente
                e, opcionalmente, a um ASN.
            </p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('prefixes.store') }}">
            @include('prefixes._form', [
                'submitLabel' => 'Cadastrar prefixo',
                'cancelUrl' => $prefix->ip_version === 6
                    ? route('prefixes.ipv6')
                    : route('prefixes.ipv4'),
            ])
        </form>
    </section>
@endsection
