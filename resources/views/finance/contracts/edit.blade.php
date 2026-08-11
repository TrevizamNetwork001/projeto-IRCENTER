@extends('layouts.app')

@section('title', 'Editar cobrança recorrente')

@section('content')
    @include('finance._nav')

    @php($currentItem = $contract->items->firstWhere('active', true) ?: $contract->items->first())

    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Cliente · Financeiro
            </div>
            <h1>Editar cobrança recorrente</h1>
            <p>{{ $contract->client_trade_name_snapshot ?: $contract->client_legal_name_snapshot }}</p>
        </div>
        <div class="page-actions">
            <a class="button button-secondary" href="{{ route('finance.clients.show', $contract->core_client_id) }}">
                Voltar ao cliente
            </a>
        </div>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <span class="panel-eyebrow">Cobrança recorrente</span>
                <h2>Configuração vigente</h2>
            </div>
        </header>

        <form method="POST" action="{{ route('finance.contracts.update', $contract) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field-group field-span-2">
                    <label for="billing_item_id">Item de cobrança <span>*</span></label>
                    <select id="billing_item_id" class="form-control" name="billing_item_id" required>
                        <option value="">Selecione...</option>
                        @foreach ($billingItems as $billingItem)
                            <option
                                value="{{ $billingItem->id }}"
                                @selected((int) old('billing_item_id', $currentItem?->billing_item_id) === $billingItem->id)
                            >
                                {{ $billingItem->name }} — R$ {{ number_format((float) $billingItem->default_amount, 2, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                    @error('billing_item_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field-group">
                    <label for="quantity">Quantidade <span>*</span></label>
                    <input id="quantity" class="form-control" name="quantity" value="{{ old('quantity', $currentItem?->quantity ?: '1') }}" required>
                    @error('quantity')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field-group">
                    <label for="unit_amount">Valor <span>*</span></label>
                    <input id="unit_amount" class="form-control" name="unit_amount" value="{{ old('unit_amount', $currentItem?->unit_amount) }}" required>
                    @error('unit_amount')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field-group">
                    <label for="generation_day">Dia de geração <span>*</span></label>
                    <input id="generation_day" class="form-control" name="generation_day" type="number" min="1" max="31" value="{{ old('generation_day', $contract->generation_day) }}" required>
                    @error('generation_day')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field-group">
                    <label for="due_day">Dia de vencimento <span>*</span></label>
                    <input id="due_day" class="form-control" name="due_day" type="number" min="1" max="31" value="{{ old('due_day', $contract->due_day) }}" required>
                    @error('due_day')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="field-group field-span-2">
                    <label for="billing_email_override">Destinatário financeiro</label>
                    <input id="billing_email_override" class="form-control" name="billing_email_override" type="email" value="{{ old('billing_email_override', $contract->billing_email_override) }}" placeholder="Usar contato financeiro do cliente">
                    @error('billing_email_override')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <label class="checkbox-row field-span-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $contract->status === 'active'))>
                    <span>Configuração ativa</span>
                </label>
            </div>

            <p class="field-help">
                A alteração vale para os próximos ciclos. Faturas já emitidas preservam seus itens e valores.
            </p>

            <div class="form-actions">
                <button class="button button-primary" type="submit">Salvar configuração</button>
            </div>
        </form>
    </section>
@endsection
