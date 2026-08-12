@extends('layouts.app')

@section('title', $client->displayName().' — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Cliente
            </div>

            <h1>{{ $client->displayName() }}</h1>

            <p>
                {{ $client->legal_name }}
                · {{ $client->client_code }}
            </p>
        </div>

        @if (auth()->user()->isAdministrator())
            <div class="page-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('clients.edit', $client) }}"
                >
                    Editar
                </a>

                <form
                    method="POST"
                    action="{{ route('clients.toggle-active', $client) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button class="button button-secondary" type="submit">
                        {{ $client->active ? 'Desativar' : 'Ativar' }}
                    </button>
                </form>
            </div>
        @endif
    </section>

    @if (session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="panel finance-client-summary">
        <header class="panel-header"><div><span class="panel-eyebrow">Cliente</span><h2>Financeiro</h2></div>@if(auth()->user()->isAdministrator())<a class="button button-primary" href="{{ route('finance.invoices.create',['client_id'=>$client->id]) }}">Gerar cobrança avulsa</a>@endif</header>
        <div class="finance-metrics"><div><span>Contratos ativos</span><strong>{{ $financeContracts->where('status','active')->count() }}</strong></div><div><span>Total em aberto</span><strong>R$ {{ number_format((float)$financeOpenTotal,2,',','.') }}</strong></div><div><span>Faturas recentes</span><strong>{{ $financeInvoices->count() }}</strong></div><div><span>Próxima cobrança</span><strong>{{ ($next=$financeContracts->where('status','active')->sortBy('generation_day')->first()) ? 'Dia '.$next->generation_day : '—' }}</strong></div></div>
        <div class="page-actions"><a class="button button-secondary" href="{{ route('finance.contracts.index',['search'=>$client->client_code]) }}">Ver contratos</a><a class="button button-secondary" href="{{ route('finance.invoices.index',['search'=>$client->client_code]) }}">Ver faturas e pagamentos</a>@if(auth()->user()->isAdministrator())<a class="button button-secondary" href="{{ route('finance.contracts.create',['client_id'=>$client->id]) }}">Novo contrato</a>@endif</div>
    </section>
    @if(config('finance_fiscal.fiscal.enabled', false))
        <section class="panel"><header class="panel-header"><div><span class="panel-eyebrow">Fiscal</span><h2>Cadastro e documentos fiscais</h2></div><div class="page-actions">@if(auth()->user()->isAdministrator())<a class="button button-primary" href="{{ route('fiscal.documents.create',['client_id'=>$client->id]) }}">Novo documento fiscal</a><a class="button button-secondary" href="{{ route('clients.fiscal.edit', $client) }}">Editar dados fiscais</a>@endif</div></header><p>Cadastro: <strong>{{ $fiscalProfile?->document && $fiscalProfile?->municipality_code ? 'Apto para revisão' : 'Incompleto' }}</strong> · Documentos: <strong>{{ $fiscalDocumentCount }}</strong></p>@foreach($fiscalDocuments as $fiscalDocument)<p><a class="inline-link" href="{{ route('fiscal.documents.show',$fiscalDocument) }}">{{ $fiscalDocument->competence_date->format('m/Y') }} · {{ $fiscalDocument->nfse_number ?: $fiscalDocument->public_id }} · {{ $fiscalDocument->status->value }}</a></p>@endforeach</section>
    @endif
    <section class="details-grid">
        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Cadastro</span>
                    <h2>Informações principais</h2>
                </div>

                <span class="status-pill {{ $client->active ? 'is-active' : 'is-inactive' }}">
                    {{ $client->active ? 'Ativo' : 'Inativo' }}
                </span>
            </header>

            <dl class="details-list">
                <div>
                    <dt>Código interno</dt>
                    <dd class="table-mono">
                        {{ $client->client_code }}
                    </dd>
                </div>

                <div>
                    <dt>Número do contrato</dt>
                    <dd class="table-mono">
                        {{ $client->contract_number ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt>Razão social</dt>
                    <dd>{{ $client->legal_name }}</dd>
                </div>

                <div>
                    <dt>Nome fantasia</dt>
                    <dd>{{ $client->trade_name ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Documento</dt>
                    <dd class="table-mono">{{ $client->formattedDocument() }}</dd>
                </div>

                <div>
                    <dt>País</dt>
                    <dd>{{ $client->country }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Contato</span>
                    <h2>Localização e canais</h2>
                </div>
            </header>

            <dl class="details-list">
                <div>
                    <dt>E-mail</dt>
                    <dd>{{ $client->email ?: '—' }}</dd>
                </div>

                <div>
                    <dt>Telefone</dt>
                    <dd>{{ $client->formattedPhone() }}</dd>
                </div>

                <div>
                    <dt>Site</dt>
                    <dd>
                        @if ($client->website)
                            <a
                                class="inline-link"
                                href="{{ $client->website }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ $client->website }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt>Endereço</dt>
                    <dd>
                        @if (
                            $client->street
                            || $client->address_number
                            || $client->district
                            || $client->city
                            || $client->state
                            || $client->postal_code
                        )
                            <span>
                                {{ collect([
                                    $client->street,
                                    $client->address_number,
                                ])->filter()->implode(', ') }}
                            </span>

                            @if (
                                $client->address_complement
                                || $client->district
                            )
                                <span class="table-secondary-text">
                                    {{ collect([
                                        $client->address_complement,
                                        $client->district,
                                    ])->filter()->implode(' — ') }}
                                </span>
                            @endif

                            <span class="table-secondary-text">
                                {{ collect([
                                    $client->city,
                                    $client->state,
                                ])->filter()->implode(' / ') }}

                                @if ($client->postal_code)
                                    · CEP {{ $client->formattedPostalCode() }}
                                @endif

                                · {{ $client->country }}
                            </span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel details-card details-card-wide client-contacts-card">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Relacionamento</span>
                    <h2>CONTATOS DO CLIENTE</h2>
                </div>
                @if (auth()->user()->isAdministrator())
                    <a class="button button-secondary" href="{{ route('clients.contacts.create', $client) }}">+ Adicionar contato</a>
                @endif
            </header>

            @if ($client->contacts->isEmpty())
                <div class="client-contacts-empty">
                    <p>Nenhum contato estruturado cadastrado.</p>
                    @if (auth()->user()->isAdministrator())
                        <a class="inline-link" href="{{ route('clients.contacts.create', $client) }}">+ Adicionar contato</a>
                    @endif
                </div>
            @else
                <div class="client-contact-list">
                    @foreach ($client->contacts as $contact)
                        <section class="client-contact-item">
                            <div class="client-contact-main">
                                <div class="client-contact-heading">
                                    <strong>{{ $contact->typeLabel() }}</strong>
                                    <span class="status-pill {{ $contact->active ? 'is-active' : 'is-inactive' }}">{{ $contact->active ? 'Ativo' : 'Inativo' }}</span>
                                    @if ($contact->is_primary)
                                        <span class="contact-primary-pill">Principal</span>
                                    @endif
                                </div>
                                <div class="client-contact-name">{{ $contact->name }}</div>
                                <a class="inline-link" href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                                @if ($contact->phone)
                                    <span class="table-secondary-text">{{ $contact->formattedPhone() }}</span>
                                @endif
                            </div>

                            @if (auth()->user()->isAdministrator())
                                <div class="client-contact-actions">
                                    <a class="button button-secondary" href="{{ route('clients.contacts.edit', [$client, $contact]) }}">Editar</a>
                                    <form method="POST" action="{{ route('clients.contacts.toggle-active', [$client, $contact]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="button button-secondary" type="submit">{{ $contact->active ? 'Desativar' : 'Ativar' }}</button>
                                    </form>
                                    @if ($contact->active)
                                        <form method="POST" action="{{ route('clients.contacts.toggle-primary', [$client, $contact]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button button-secondary" type="submit">{{ $contact->is_primary ? 'Desmarcar principal' : 'Marcar principal' }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="panel details-card details-card-wide">
            <header class="panel-header">
                <div>
                    <span class="panel-eyebrow">Contexto</span>
                    <h2>Observações</h2>
                </div>
            </header>

            <div class="notes-content">
                {{ $client->notes ?: 'Nenhuma observação cadastrada.' }}
            </div>
        </article>
    </section>
@endsection
