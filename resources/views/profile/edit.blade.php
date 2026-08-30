@extends('layouts.app')

@section('title', 'Meu perfil | IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <span class="page-eyebrow">Conta pessoal</span>
            <h1>Meu perfil</h1>
            <p>Gerencie seus dados, aparência e segurança.</p>
        </div>
    </section>

    <section class="panel profile-container">
        <header class="profile-identity">
            <div class="profile-photo-wrap">
                <x-user-avatar :user="$user" size="xlarge"/>

                <button
                    type="button"
                    class="profile-camera-button"
                    aria-label="Alterar imagem do perfil"
                    title="Alterar imagem do perfil"
                    onclick="document.getElementById('avatar-dialog').showModal()"
                >
                    <x-icon name="camera" size="14"/>
                </button>
            </div>

            <div class="profile-identity-text">
                <h1>{{ $user->name }}</h1>
                <p>{{ $user->email }}</p>
                <span class="role-pill is-{{ $user->role }}">
                    {{ $user->roleLabel() }}
                </span>
            </div>
        </header>

        <div class="profile-section">
            <h2 class="profile-section-title">Informações da conta</h2>

            <div class="form-grid">
                <div class="field-group">
                    <label for="account-name">Nome</label>

                    <input
                        id="account-name"
                        class="form-control"
                        type="text"
                        value="{{ $user->name }}"
                        readonly
                    >
                </div>

                <div class="field-group">
                    <label for="account-email">E-mail de acesso</label>

                    <input
                        id="account-email"
                        class="form-control"
                        type="text"
                        value="{{ $user->email }}"
                        readonly
                    >
                </div>
            </div>

            <small class="field-hint">
                Nome e e-mail são definidos por um administrador. Fale
                com a equipe responsável para alterá-los.
            </small>

            <dl class="details-list">
                <div>
                    <dt>Último acesso</dt>
                    <dd>
                        {{ $user->last_login_at
                            ? app(\App\Support\BusinessClock::class)
                                ->toBusinessTimezone($user->last_login_at)
                                ->format('d/m/Y H:i')
                            : 'Ainda não registrado' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="profile-section">
            <h2 class="profile-section-title">Aparência</h2>

            <div
                class="segmented-control"
                id="theme-segmented-control"
                role="group"
                aria-label="Tema da plataforma"
            >
                <button type="button" data-theme-choice="light">
                    Claro
                </button>

                <button type="button" data-theme-choice="dark">
                    Escuro
                </button>
            </div>
        </div>

        <div class="profile-section">
            <h2 class="profile-section-title">Segurança</h2>

            <div class="profile-security-row">
                <div>
                    <strong>Senha</strong>

                    <div class="table-secondary-text">
                        Última alteração:
                        {{ $user->password_changed_at
                            ? app(\App\Support\BusinessClock::class)
                                ->toBusinessTimezone(
                                    $user->password_changed_at
                                )
                                ->format('d/m/Y H:i')
                            : 'Não informado' }}
                    </div>
                </div>

                <a
                    class="button button-secondary"
                    href="{{ route('profile.password.edit') }}"
                >
                    Alterar senha
                </a>
            </div>
        </div>
    </section>

    <dialog id="avatar-dialog" class="avatar-dialog">
        <div class="avatar-dialog-inner">
            <div class="avatar-dialog-header">
                <h2>Imagem do perfil</h2>

                <button
                    type="button"
                    class="avatar-dialog-close"
                    aria-label="Fechar"
                    onclick="document.getElementById('avatar-dialog').close()"
                >
                    <x-icon name="close" size="16"/>
                </button>
            </div>

            <div class="avatar-dialog-preview">
                <x-user-avatar :user="$user" size="large"/>

                <div>
                    <strong>{{ $user->name }}</strong>
                    <div class="table-secondary-text">
                        {{ $user->hasPhotoAvatar()
                            ? 'Usando foto de perfil'
                            : ($user->hasThemedAvatar()
                                ? 'Usando avatar '.$user->avatarLabel()
                                : 'Usando iniciais do nome') }}
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.avatar.update') }}">
                @csrf
                @method('PUT')

                <div class="avatar-picker">
                    <label class="avatar-option">
                        <input
                            name="avatar_key"
                            type="radio"
                            value=""
                            @checked(! $user->hasThemedAvatar())
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
                                    $user->hasThemedAvatar()
                                    && $user->avatar_key === $avatarKey
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
                    <button
                        type="button"
                        class="button button-secondary"
                        onclick="document.getElementById('avatar-dialog').close()"
                    >
                        Cancelar
                    </button>

                    <button class="button button-primary" type="submit">
                        Aplicar
                    </button>
                </div>
            </form>

            <div class="avatar-photo-section">
                <strong>Minha foto</strong>
                <p class="field-hint">
                    JPEG, PNG ou WebP · até 2&nbsp;MB.
                </p>

                <div class="avatar-photo-actions">
                    <form
                        method="POST"
                        action="{{ route('profile.photo.store') }}"
                        enctype="multipart/form-data"
                    >
                        @csrf

                        <label class="button button-secondary">
                            Escolher foto
                            <input
                                class="visually-hidden-input"
                                type="file"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp"
                                onchange="this.form.requestSubmit()"
                            >
                        </label>
                    </form>

                    @if ($user->avatar_photo_path)
                        <form
                            method="POST"
                            action="{{ route('profile.photo.destroy') }}"
                        >
                            @csrf
                            @method('DELETE')

                            <button class="button button-ghost" type="submit">
                                Remover foto
                            </button>
                        </form>
                    @endif
                </div>

                @error('photo')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </dialog>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const control = document.getElementById(
                'theme-segmented-control'
            );

            if (! control) {
                return;
            }

            const sync = () => {
                const current =
                    document.documentElement.dataset.theme || 'dark';

                control.querySelectorAll('button').forEach((button) => {
                    button.classList.toggle(
                        'is-active',
                        button.dataset.themeChoice === current
                    );
                });
            };

            control.querySelectorAll('button').forEach((button) => {
                button.addEventListener('click', () => {
                    const nextTheme = button.dataset.themeChoice;

                    document.documentElement.dataset.theme = nextTheme;

                    try {
                        localStorage.setItem('ircenter-theme', nextTheme);
                    } catch (error) {
                        // O tema continua funcionando durante a sessão.
                    }

                    sync();
                });
            });

            sync();
        })();
    </script>
@endsection
