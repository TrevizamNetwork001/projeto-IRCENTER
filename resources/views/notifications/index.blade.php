@extends('layouts.app')

@section('title', 'Notificações — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Central de operações
            </div>

            <h1>Notificações</h1>

            <p>
                Acompanhe pendências e avisos gerados a partir dos
                dados reais da plataforma.
            </p>
        </div>

        <div class="page-actions">
            @if ($unreadTotal > 0)
                <form
                    method="POST"
                    action="{{ route('notifications.read-all') }}"
                >
                    @csrf
                    @method('PATCH')

                    <button class="button button-secondary" type="submit">
                        Marcar todas como lidas
                    </button>
                </form>
            @endif
        </div>
    </section>

    @if (session('success'))
        <div
            class="alert alert-success"
            data-auto-dismiss="5000"
        >
            {{ session('success') }}
        </div>
    @endif

    <section class="notification-summary">
        <article class="notification-summary-card">
            <span>Não lidas</span>
            <strong>{{ $unreadTotal }}</strong>
        </article>

        <nav
            class="notification-filters"
            aria-label="Filtros de notificações"
        >
            <a
                class="{{ $status === 'all' ? 'is-active' : '' }}"
                href="{{ route('notifications.index') }}"
            >
                Todas
            </a>

            <a
                class="{{ $status === 'unread' ? 'is-active' : '' }}"
                href="{{ route('notifications.index', [
                    'status' => 'unread',
                ]) }}"
            >
                Não lidas
            </a>
        </nav>
    </section>

    <section class="panel">
        @if ($notifications->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon">
                    <x-icon name="bell" size="24"/>
                </div>

                <div>
                    <strong>
                        {{ $status === 'unread'
                            ? 'Nenhuma notificação não lida'
                            : 'Nenhuma notificação ativa' }}
                    </strong>

                    <span>
                        As próximas pendências operacionais aparecerão
                        nesta central.
                    </span>
                </div>
            </div>
        @else
            <div class="notification-page-list">
                @foreach ($notifications as $notification)
                    <article
                        class="notification-page-item
                            {{ $notification->isUnread()
                                ? 'is-unread'
                                : '' }}"
                    >
                        <div
                            class="notification-priority-dot
                                is-{{ $notification->priority }}"
                        ></div>

                        <div class="notification-page-content">
                            <div class="notification-page-heading">
                                <div>
                                    <span
                                        class="notification-priority-label
                                            is-{{ $notification->priority }}"
                                    >
                                        {{ $notification->priorityLabel() }}
                                    </span>

                                    <h2>{{ $notification->title }}</h2>
                                </div>

                                <time
                                    datetime="{{ $notification->updated_at }}"
                                >
                                    {{ $notification->updated_at
                                        ?->diffForHumans() }}
                                </time>
                            </div>

                            <p>{{ $notification->message }}</p>

                            <div class="notification-page-actions">
                                @if ($notification->action_url)
                                    <a
                                        class="button button-secondary"
                                        href="{{ $notification->action_url }}"
                                    >
                                        Ver recurso
                                    </a>
                                @endif

                                @if ($notification->isUnread())
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'notifications.read',
                                            $notification
                                        ) }}"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <button
                                            class="button button-ghost"
                                            type="submit"
                                        >
                                            Marcar como lida
                                        </button>
                                    </form>
                                @else
                                    <span class="notification-read-state">
                                        Lida
                                    </span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            {{ $notifications->links() }}
        @endif
    </section>
@endsection
