@csrf

<div class="form-grid">
    <div class="field-group field-span-2">
        <label for="legal_name">Razão social <span>*</span></label>
        <input
            id="legal_name"
            class="form-control"
            name="legal_name"
            type="text"
            value="{{ old('legal_name', $client->legal_name) }}"
            maxlength="255"
            required
        >
        @error('legal_name')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="trade_name">Nome fantasia</label>
        <input
            id="trade_name"
            class="form-control"
            name="trade_name"
            type="text"
            value="{{ old('trade_name', $client->trade_name) }}"
            maxlength="255"
        >
        @error('trade_name')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="document">Documento</label>
        <input
            id="document"
            class="form-control"
            name="document"
            type="text"
            value="{{ old('document', $client->document) }}"
            maxlength="30"
            placeholder="CNPJ, CPF ou identificador"
        >
        @error('document')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="email">E-mail</label>
        <input
            id="email"
            class="form-control"
            name="email"
            type="email"
            value="{{ old('email', $client->email) }}"
            maxlength="255"
        >
        @error('email')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="phone">Telefone</label>
        <input
            id="phone"
            class="form-control"
            name="phone"
            type="text"
            value="{{ old('phone', $client->phone) }}"
            maxlength="30"
        >
        @error('phone')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="website">Site</label>
        <input
            id="website"
            class="form-control"
            name="website"
            type="url"
            value="{{ old('website', $client->website) }}"
            maxlength="255"
            placeholder="https://empresa.example"
        >
        @error('website')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="city">Cidade</label>
        <input
            id="city"
            class="form-control"
            name="city"
            type="text"
            value="{{ old('city', $client->city) }}"
            maxlength="100"
        >
        @error('city')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="state">Estado</label>
        <input
            id="state"
            class="form-control"
            name="state"
            type="text"
            value="{{ old('state', $client->state) }}"
            maxlength="100"
        >
        @error('state')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="country">País <span>*</span></label>
        <input
            id="country"
            class="form-control"
            name="country"
            type="text"
            value="{{ old('country', $client->country ?: 'BR') }}"
            minlength="2"
            maxlength="2"
            required
        >
        @error('country')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-checkbox-group">
        <label class="checkbox-label">
            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $client->active ?? true))
            >
            <span>
                <strong>Cliente ativo</strong>
                <small>Disponível para vínculos e operações</small>
            </span>
        </label>
        @error('active')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="notes">Observações</label>
        <textarea
            id="notes"
            class="form-control"
            name="notes"
            rows="5"
            maxlength="5000"
        >{{ old('notes', $client->notes) }}</textarea>
        @error('notes')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-actions">
    <a class="button button-secondary" href="{{ $cancelUrl }}">
        Cancelar
    </a>

    <button class="button button-primary" type="submit">
        {{ $submitLabel }}
    </button>
</div>
