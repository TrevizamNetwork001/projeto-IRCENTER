@extends('layouts.app')

@section('title', 'Editar usuário | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Administração de usuários
            </div>

            <h1>{{ $managedUser->name }}</h1>

            <p>{{ $managedUser->email }}</p>
        </div>

        <div class="page-actions">
            <a class="button button-secondary" href="{{ route('users.index') }}">
                Voltar à lista
            </a>

            <form
                method="POST"
                action="{{ route('users.toggle-active', $managedUser) }}"
            >
                @csrf
                @method('PATCH')

                <button class="button button-secondary" type="submit">
                    {{ $managedUser->active ? 'Bloquear' : 'Ativar' }}
                </button>
            </form>
        </div>
    </section>

    <div class="split-layout">
        <div class="split-layout-main">
            <section class="panel">
                <form
                    method="POST"
                    action="{{ route('users.update', $managedUser) }}"
                >
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
