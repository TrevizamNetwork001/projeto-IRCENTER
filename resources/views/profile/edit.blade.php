@extends('layouts.app')

@section('title', 'Meu perfil | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">Conta pessoal</span>
            <h1>Meu perfil</h1>
            <p>
                Consulte seus dados e personalize sua identificação
                na plataforma.
            </p>
        </div>

        <a
            class="button button-secondary"
            href="{{ route('profile.password.edit') }}"
        >
            Alterar minha senha
        </a>
    </section>

    @if (session('success'))
        <div
            class="alert-success"
            data-auto-dismiss="5000"
            role="status"
        >
            {{ session('success') }}
        </div>
    @endif

    <section class="profile-layout">
        <div class="profile-sidebar">
            <article class="panel profile-summary-card">
                <x-user-avatar :user="$user" size="xlarge"/>

                <div>
                    <h2>{{ $user->name }}</h2>

                    <span class="status-pill is-active">
                        {{ $user->roleLabel() }}
                    </span>
                </div>
            </article>

            <article class="panel profile-account-card">
                <header class="panel-header">
                    <div>
                        <span class="panel-eyebrow">Conta</span>
                        <h2>Dados da conta</h2>
                    </div>
                </header>

                <dl class="details-list">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $user->name }}</dd>
                    </div>

                    <div>
                        <dt>E-mail de acesso</dt>
                        <dd class="profile-account-value">
                            {{ $user->email }}
                        </dd>
                    </div>

                    <div>
                        <dt>Perfil</dt>
                        <dd>{{ $user->roleLabel() }}</dd>
                    </div>

                    <div>
                        <dt>Último acesso</dt>
                        <dd>
                            {{ $user->last_login_at
                                ? $user->last_login_at->format('d/m/Y H:i')
                                : 'Ainda não registrado' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Senha alterada em</dt>
                        <dd>
                            {{ $user->password_changed_at
                                ? $user->password_changed_at->format('d/m/Y H:i')
                                : 'Não informado' }}
                        </dd>
                    </div>
                </dl>
            </article>
        </div>

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
