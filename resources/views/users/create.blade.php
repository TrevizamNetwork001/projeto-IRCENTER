@extends('layouts.app')

@section('title', 'Novo usuário | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Administração de usuários
            </div>

            <h1>Novo usuário</h1>

            <p>Conceda acesso à plataforma para um novo usuário.</p>
        </div>

        <a class="button button-secondary" href="{{ route('users.index') }}">
            Voltar à lista
        </a>
    </section>

    <div class="split-layout">
        <div class="split-layout-main">
            <section class="panel">
                <form method="POST" action="{{ route('users.store') }}">
                    @include('users._form')
                </form>
            </section>
        </div>

        <aside class="tip-card">
            <span class="tip-card-icon">
                <x-icon name="shield" size="17"/>
            </span>

            <div>
                <h3>Dica de segurança</h3>
                <p>
                    Mantenha o princípio do privilégio mínimo: atribua o
                    perfil "Operador" ou "Somente leitura" sempre que o
                    acesso de administrador não for necessário.
                </p>
            </div>
        </aside>
    </div>
@endsection
