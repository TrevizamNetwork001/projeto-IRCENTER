@csrf

<div class="form-grid">
    <div class="field-group">
        <label for="client_id">Cliente <span>*</span></label>
        <select id="client_id" class="form-control" name="client_id" required>
            <option value="">Selecione</option>
            @foreach ($clients as $client)
                <option
                    value="{{ $client->id }}"
                    @selected((string) old('client_id', $autonomousSystem->client_id) === (string) $client->id)
                >
                    {{ $client->displayName() }}
                </option>
            @endforeach
        </select>
        @error('client_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="asn">ASN <span>*</span></label>
        <input
            id="asn"
            class="form-control"
            name="asn"
            type="text"
            value="{{ old('asn', $autonomousSystem->asn ? 'AS'.$autonomousSystem->asn : '') }}"
            placeholder="AS264001"
            required
        >
        @error('asn')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="name">Nome <span>*</span></label>
        <input
            id="name"
            class="form-control"
            name="name"
            type="text"
            value="{{ old('name', $autonomousSystem->name) }}"
            maxlength="255"
            required
        >
        @error('name')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="description">Descrição</label>
        <textarea
            id="description"
            class="form-control"
            name="description"
            rows="4"
            maxlength="5000"
        >{{ old('description', $autonomousSystem->description) }}</textarea>
        @error('description')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="rir">RIR</label>
        <select id="rir" class="form-control" name="rir">
            <option value="">Selecione</option>
            @foreach (['AFRINIC', 'APNIC', 'ARIN', 'LACNIC', 'RIPE NCC', 'OTHER'] as $rir)
                <option
                    value="{{ $rir }}"
                    @selected(old('rir', $autonomousSystem->rir) === $rir)
                >
                    {{ $rir }}
                </option>
            @endforeach
        </select>
        @error('rir')
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
            value="{{ old('country', $autonomousSystem->country ?: 'BR') }}"
            minlength="2"
            maxlength="2"
            required
        >
        @error('country')
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
            value="{{ old('website', $autonomousSystem->website) }}"
            maxlength="255"
            placeholder="https://empresa.example"
        >
        @error('website')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="noc_contact">Contato do NOC</label>
        <input
            id="noc_contact"
            class="form-control"
            name="noc_contact"
            type="text"
            value="{{ old('noc_contact', $autonomousSystem->noc_contact) }}"
            maxlength="255"
        >
        @error('noc_contact')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="noc_email">E-mail do NOC</label>
        <input
            id="noc_email"
            class="form-control"
            name="noc_email"
            type="email"
            value="{{ old('noc_email', $autonomousSystem->noc_email) }}"
            maxlength="255"
        >
        @error('noc_email')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="noc_phone">Telefone do NOC</label>
        <input
            id="noc_phone"
            class="form-control"
            name="noc_phone"
            type="text"
            value="{{ old('noc_phone', $autonomousSystem->noc_phone) }}"
            maxlength="30"
        >
        @error('noc_phone')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-checkbox-group">
        <label class="checkbox-label">
            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $autonomousSystem->active ?? true))
            >
            <span>
                <strong>ASN ativo</strong>
                <small>Disponível para prefixos e monitoramento</small>
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
        >{{ old('notes', $autonomousSystem->notes) }}</textarea>
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
