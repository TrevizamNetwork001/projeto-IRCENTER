@csrf

@if ($errors->has('finance'))
    <div class="field-error">
        {{ $errors->first('finance') }}
    </div>
@endif

<div class="form-grid">
    <div class="field-group field-span-2">
        <label for="client_id">
            Cliente <span>*</span>
        </label>

        <select
            id="client_id"
            class="form-control"
            name="client_id"
            required
        >
            <option value="">Selecione...</option>

            @foreach ($clients as $client)
                <option
                    value="{{ $client->id }}"
                    @selected(
                        (string) old('client_id')
                        === (string) $client->id
                    )
                >
                    {{
                        $client->trade_name
                        ?: $client->legal_name
                    }}
                    — {{ $client->client_code }}
                </option>
            @endforeach
        </select>

        @error('client_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="generation_day">
            Dia de geração <span>*</span>
        </label>

        <input
            id="generation_day"
            class="form-control"
            name="generation_day"
            type="number"
            min="1"
            max="31"
            value="{{ old('generation_day', 5) }}"
            required
        >

        @error('generation_day')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="due_day">
            Dia de vencimento <span>*</span>
        </label>

        <input
            id="due_day"
            class="form-control"
            name="due_day"
            type="number"
            min="1"
            max="31"
            value="{{ old('due_day', 20) }}"
            required
        >

        @error('due_day')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="billing_email_override">
            E-mail financeiro específico
        </label>

        <input
            id="billing_email_override"
            class="form-control"
            name="billing_email_override"
            type="email"
            maxlength="255"
            value="{{ old('billing_email_override') }}"
            placeholder="Opcional"
        >

        <small class="field-help">
            Se vazio, será usado o contato financeiro
            estruturado do cliente.
        </small>

        @error('billing_email_override')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="starts_on">Início</label>

        <input
            id="starts_on"
            class="form-control"
            name="starts_on"
            type="date"
            value="{{ old('starts_on') }}"
        >

        @error('starts_on')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="ends_on">Fim</label>

        <input
            id="ends_on"
            class="form-control"
            name="ends_on"
            type="date"
            value="{{ old('ends_on') }}"
        >

        @error('ends_on')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <h3>Primeiro item do contrato</h3>
    </div>

    <div class="field-group">
        <label for="service_code">
            Código do serviço
        </label>

        <input
            id="service_code"
            class="form-control"
            name="service_code"
            type="text"
            maxlength="100"
            value="{{ old('service_code') }}"
        >

        @error('service_code')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="quantity">
            Quantidade <span>*</span>
        </label>

        <input
            id="quantity"
            class="form-control"
            name="quantity"
            type="text"
            inputmode="decimal"
            value="{{ old('quantity', '1') }}"
            required
        >

        @error('quantity')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="description">
            Descrição <span>*</span>
        </label>

        <input
            id="description"
            class="form-control"
            name="description"
            type="text"
            maxlength="255"
            value="{{ old('description') }}"
            required
        >

        @error('description')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="unit_amount">
            Valor unitário <span>*</span>
        </label>

        <input
            id="unit_amount"
            class="form-control"
            name="unit_amount"
            type="text"
            inputmode="decimal"
            value="{{ old('unit_amount') }}"
            placeholder="150.00"
            required
        >

        <small class="field-help">
            Aceita vírgula ou ponto como separador decimal.
        </small>

        @error('unit_amount')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-actions">
    <a
        class="button button-secondary"
        href="{{ route('finance.contracts.index') }}"
    >
        Cancelar
    </a>

    <button class="button button-primary" type="submit">
        Criar contrato
    </button>
</div>
