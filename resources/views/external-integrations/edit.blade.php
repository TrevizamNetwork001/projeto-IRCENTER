@extends('layouts.app')

@section('title', 'Editar '.$integration->name.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Integrações externas
            </div>

            <h1>Editar integração</h1>

            <p>{{ $integration->name }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form
            method="POST"
            action="{{ route(
                'external-integrations.update',
                $integration
            ) }}"
        >
            @method('PUT')
            @include('external-integrations._form')
        </form>
    </section>
@endsection
