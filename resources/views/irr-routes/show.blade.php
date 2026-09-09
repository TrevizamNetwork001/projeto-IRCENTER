@extends('layouts.app')

@section('title', $route->prefix.' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1 class="table-mono">{{ $route->prefix }}</h1>
            <p>AS{{ $route->origin_asn }} — {{ $route->maintainer->mntner }}</p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a class="button button-secondary" href="{{ route('irr-routes.edit', $route) }}">Editar</a>

                <button
                    class="button button-primary"
                    type="button"
                    data-rpsl-dialog-open
                    @disabled($rpslError !== null)
                    @if ($rpslError !== null) title="Corrija o RPSL abaixo antes de publicar" @endif
                >
                    Publicar no TC
                </button>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if (session('warning'))
        <div class="alert-error">⚠ {{ session('warning') }}</div>
    @endif

    @if (session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <section class="panel">
        <dl class="detail-grid">
            <div>
                <dt>Status</dt>
                <dd>
                    <span class="status-pill {{ $route->status === 'published' ? 'is-active' : 'is-inactive' }}">
                        {{ $route->status }}
                    </span>
                </dd>
            </div>

            <div>
                <dt>Última publicação</dt>
                <dd>{{ $route->last_published_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </div>

            <div>
                <dt>Descrição</dt>
                <dd>{{ $route->descr ?? '—' }}</dd>
            </div>

            @if (! empty($route->member_of))
                <div>
                    <dt>Member-of</dt>
                    <dd class="table-mono">{{ implode(', ', $route->member_of) }}</dd>
                </div>
            @endif

            @if (! empty($route->notify))
                <div>
                    <dt>Notify</dt>
                    <dd class="table-mono">{{ implode(', ', $route->notify) }}</dd>
                </div>
            @endif

            @if ($route->last_error)
                <div>
                    <dt>Último erro</dt>
                    <dd>{{ $route->last_error }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="panel">
        <h2>RPSL gerado</h2>

        @if ($rpslError !== null)
            <div class="alert-error">
                Não foi possível gerar o RPSL: {{ $rpslError }}
            </div>
        @else
            <pre class="irr-raw-object">{{ $rpslPreview }}</pre>
        @endif
    </section>

    <section class="panel">
        <h2>Histórico de submissões</h2>

        @if ($route->submissions->isEmpty())
            <p class="table-secondary-text">Este objeto ainda não foi enviado ao TC.</p>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Operação</th>
                            <th>Resultado</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($route->submissions as $submission)
                            <tr>
                                <td>{{ $submission->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $submission->operation }}</td>
                                <td>
                                    <span class="status-pill {{ $submission->successful ? 'is-active' : 'is-inactive' }}">
                                        {{ $submission->successful ? 'Sucesso' : 'Falha' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if (auth()->user()->isAdministrator())
        <section class="panel">
            <h2>Remover do TC</h2>
            <p class="table-secondary-text">
                Envia uma solicitação de remoção do objeto na base do TC. O
                registro no IRCENTER só é removido separadamente, em
                "Editar" → excluir.
            </p>

            <form method="POST" action="{{ route('irr-routes.destroy-remote', $route) }}">
                @csrf
                @method('DELETE')
                <button class="button button-secondary" type="submit">
                    Remover objeto no TC
                </button>
            </form>
        </section>
    @endif

    @if (auth()->user()->isAdministrator() && $rpslError === null)
        <dialog id="rpsl-publish-dialog" class="rpsl-dialog">
            <div class="rpsl-dialog-inner">
                <div class="avatar-dialog-header">
                    <h2>Confirmar publicação no TC</h2>

                    <button class="avatar-dialog-close" type="button" data-rpsl-dialog-close aria-label="Fechar">
                        <x-icon name="close" size="16"/>
                    </button>
                </div>

                <pre class="irr-raw-object">{{ $rpslPreview }}</pre>

                <p>
                    Este texto substituirá integralmente o objeto no TC.
                    Atributos existentes que não aparecem aqui serão
                    removidos. Confirmar?
                </p>

                <div class="rpsl-dialog-actions">
                    <button class="button button-secondary" type="button" data-rpsl-dialog-close>
                        Cancelar
                    </button>

                    <form method="POST" action="{{ route('irr-routes.publish', $route) }}">
                        @csrf
                        <button class="button button-primary" type="submit">
                            Confirmar publicação
                        </button>
                    </form>
                </div>
            </div>
        </dialog>

        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            (() => {
                const dialog = document.getElementById('rpsl-publish-dialog');

                if (! dialog) {
                    return;
                }

                document.querySelectorAll('[data-rpsl-dialog-open]').forEach((button) => {
                    button.addEventListener('click', () => dialog.showModal());
                });

                document.querySelectorAll('[data-rpsl-dialog-close]').forEach((button) => {
                    button.addEventListener('click', () => dialog.close());
                });
            })();
        </script>
    @endif
@endsection
