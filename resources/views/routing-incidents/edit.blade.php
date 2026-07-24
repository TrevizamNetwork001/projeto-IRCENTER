@extends('layouts.app')

@section('title', 'Editar '.$incident->reference.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                {{ $incident->reference }}
            </div>

            <h1>Editar incidente</h1>

            <p>{{ $incident->title }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form
            method="POST"
            action="{{ route(
                'routing-incidents.update',
                $incident
            ) }}"
        >
            @method('PUT')
            @include('routing-incidents._form')
        </form>
    </section>
@endsection
