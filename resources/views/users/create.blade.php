@extends('layouts.app')

@section('title', 'Novo usuário | IRCENTER')

@section('content')
    <div class="page-heading">
        <div>
            <span class="page-eyebrow">Administração</span>
            <h1>Novo usuário</h1>
        </div>
    </div>

    <section class="panel">
        <form method="POST" action="{{ route('users.store') }}">
            @include('users._form')
        </form>
    </section>
@endsection
