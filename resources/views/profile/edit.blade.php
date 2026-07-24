@extends('layouts.app')

@section('title', 'Meu perfil | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">Conta pessoal</span>
            <h1>Meu perfil</h1>
            <p>
                Personalize o avatar exibido no topo da plataforma.
            </p>
        </div>
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <section class="profile-layout">
        <article class="panel profile-summary-card">
            <x-user-avatar :user="$user" size="xlarge"/>

            <div>
                <h2>{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
                <span class="status-pill is-active">
                    {{ $user->roleLabel() }}
                </span>
            </div>
        </article>

        <article class="panel">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Personalização</span>
                    <h2>Escolha seu avatar</h2>
                </div>
            </header>

            <form
                method="POST"
                action="{{ route('profile.avatar.update') }}"
            >
                @csrf
                @method('PUT')

                <div class="avatar-picker avatar-picker-profile">
                    <label class="avatar-option">
                        <input
                            name="avatar_key"
                            type="radio"
                            value=""
                            @checked(
                                old('avatar_key', $user->avatar_key) === null
                            )
                        >

                        <span class="avatar-option-preview">
                            <span class="user-avatar user-avatar-large">
                                {{ $user->initials() }}
                            </span>
                        </span>

                        <span class="avatar-option-label">
                            Inicial do nome
                        </span>
                    </label>

                    @foreach ($avatars as $avatarKey => $avatar)
                        <label class="avatar-option">
                            <input
                                name="avatar_key"
                                type="radio"
                                value="{{ $avatarKey }}"
                                @checked(
                                    old(
                                        'avatar_key',
                                        $user->avatar_key
                                    ) === $avatarKey
                                )
                            >

                            <span class="avatar-option-preview">
                                <span
                                    class="user-avatar user-avatar-large has-symbol"
                                    aria-hidden="true"
                                >
                                    <span class="user-avatar-symbol">
                                        {{ $avatar['symbol'] }}
                                    </span>
                                </span>
                            </span>

                            <span class="avatar-option-label">
                                {{ $avatar['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('avatar_key')
                    <div class="field-error">{{ $message }}</div>
                @enderror

                <div class="form-actions">
                    <button class="button button-primary" type="submit">
                        Salvar avatar
                    </button>
                </div>
            </form>
        </article>
    </section>
@endsection
