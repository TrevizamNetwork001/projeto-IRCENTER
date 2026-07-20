@csrf

<div class="form-grid">
    <div class="field-group">
        <label for="client_id">Cliente <span>*</span></label>

        <select
            id="client_id"
            class="form-control"
            name="client_id"
            required
        >
            <option value="">Selecione</option>

            @foreach ($clients as $client)
                <option
                    value="{{ $client->id }}"
                    @selected((string) old('client_id', $prefix->client_id) === (string) $client->id)
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
        <label for="autonomous_system_id">ASN de origem</label>

        <select
            id="autonomous_system_id"
            class="form-control"
            name="autonomous_system_id"
        >
            <option value="">Sem ASN vinculado</option>

            @foreach ($autonomousSystems as $autonomousSystem)
                <option
                    value="{{ $autonomousSystem->id }}"
                    data-client="{{ $autonomousSystem->client_id }}"
                    @selected(
                        (string) old(
                            'autonomous_system_id',
                            $prefix->autonomous_system_id
                        ) === (string) $autonomousSystem->id
                    )
                >
                    {{ $autonomousSystem->formattedAsn() }}
                    — {{ $autonomousSystem->name }}
                    — {{ $autonomousSystem->client->displayName() }}
                </option>
            @endforeach
        </select>

        <div class="field-help">
            O ASN precisa pertencer ao cliente selecionado.
        </div>

        @error('autonomous_system_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="prefix">Prefixo CIDR <span>*</span></label>

        <input
            id="prefix"
            class="form-control table-mono"
            name="prefix"
            type="text"
            value="{{ old('prefix', $prefix->prefix) }}"
            maxlength="64"
            placeholder="{{ $prefix->ip_version === 6 ? '2001:db8::/32' : '192.0.2.0/24' }}"
            required
        >

        <div class="field-help">
            O endereço será normalizado para o endereço de rede.
        </div>

        @error('prefix')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="ip_version">Versão IP <span>*</span></label>

        <select
            id="ip_version"
            class="form-control"
            name="ip_version"
            required
        >
            <option
                value="4"
                @selected((int) old('ip_version', $prefix->ip_version) === 4)
            >
                IPv4
            </option>

            <option
                value="6"
                @selected((int) old('ip_version', $prefix->ip_version) === 6)
            >
                IPv6
            </option>
        </select>

        <div class="field-help">
            A versão será confirmada automaticamente pelo CIDR.
        </div>

        @error('ip_version')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="description">Descrição</label>

        <input
            id="description"
            class="form-control"
            name="description"
            type="text"
            value="{{ old('description', $prefix->description) }}"
            maxlength="255"
            placeholder="Identificação operacional do bloco"
        >

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
                    @selected(old('rir', $prefix->rir) === $rir)
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
            value="{{ old('country', $prefix->country ?: 'BR') }}"
            minlength="2"
            maxlength="2"
            required
        >

        @error('country')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="allocation_status">Status de alocação <span>*</span></label>

        <select
            id="allocation_status"
            class="form-control"
            name="allocation_status"
            required
        >
            @foreach ([
                'allocated' => 'Alocado',
                'assigned' => 'Designado',
                'reserved' => 'Reservado',
                'legacy' => 'Legado',
                'available' => 'Disponível',
            ] as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(old(
                        'allocation_status',
                        $prefix->allocation_status
                    ) === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        @error('allocation_status')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="purpose">Finalidade</label>

        <input
            id="purpose"
            class="form-control"
            name="purpose"
            type="text"
            value="{{ old('purpose', $prefix->purpose) }}"
            maxlength="100"
            placeholder="BGP, infraestrutura, clientes..."
        >

        @error('purpose')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2 field-checkbox-group">
        <label class="checkbox-label">
            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $prefix->active ?? true))
            >

            <span>
                <strong>Prefixo ativo</strong>
                <small>Disponível para inventário e monitoramento</small>
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
        >{{ old('notes', $prefix->notes) }}</textarea>

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
