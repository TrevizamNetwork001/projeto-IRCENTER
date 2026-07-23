@extends('layouts.app')

@section('title', 'Editar objeto IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry</div>
            <h1>Editar objeto IRR</h1>
            <p class="table-mono">{{ $irrObject->object_key }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form
            method="POST"
            action="{{ route('irr-objects.update', $irrObject) }}"
        >
            @method('PUT')

            @include('irr._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('irr-objects.show', $irrObject),
            ])
        </form>
    </section>
@endsection
