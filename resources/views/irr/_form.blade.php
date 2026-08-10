@csrf

<div class="form-grid">
    <div class="field-group">
        <label for="object_type">Tipo do objeto <span>*</span></label>

        <select
            id="object_type"
            class="form-control"
            name="object_type"
            required
        >
            @foreach ([
                'route' => 'Route IPv4',
                'route6' => 'Route IPv6',
                'aut-num' => 'Aut-num',
                'as-set' => 'AS-set',
                'mntner' => 'Maintainer',
            ] as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(old('object_type', $irrObject->object_type) === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        @error('object_type')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="object_key">Chave do objeto <span>*</span></label>

        <input
            id="object_key"
            class="form-control table-mono"
            name="object_key"
            type="text"
            value="{{ old('object_key', $irrObject->object_key) }}"
            maxlength="100"
            placeholder="192.0.2.0/24, AS264001 ou MAINT-EXAMPLE"
            required
        >

        @error('object_key')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="client_id">Cliente</label>

        <select id="client_id" class="form-control" name="client_id">
            <option value="">Sem cliente vinculado</option>

            @foreach ($clients as $client)
                <option
                    value="{{ $client->id }}"
                    @selected((string) old('client_id', $irrObject->client_id) === (string) $client->id)
                >
                    {{ $client->displayName() }}
                </option>
            @endforeach
        </select>

        @error('client_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group" data-irr-field="asn">
        <label for="autonomous_system_id">ASN</label>

        <select
            id="autonomous_system_id"
            class="form-control"
            name="autonomous_system_id"
        >
            <option value="">Sem ASN vinculado</option>

            @foreach ($autonomousSystems as $autonomousSystem)
                <option
                    value="{{ $autonomousSystem->id }}"
                    @selected(
                        (string) old(
                            'autonomous_system_id',
                            $irrObject->autonomous_system_id
                        ) === (string) $autonomousSystem->id
                    )
                >
                    {{ $autonomousSystem->formattedAsn() }}
                    — {{ $autonomousSystem->name }}
                    — {{ $autonomousSystem->client->displayName() }}
                </option>
            @endforeach
        </select>

        @error('autonomous_system_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2" data-irr-field="prefix">
        <label for="prefix_id">Prefixo</label>

        <select id="prefix_id" class="form-control" name="prefix_id">
            <option value="">Sem prefixo vinculado</option>

            @foreach ($prefixes as $prefix)
                <option
                    value="{{ $prefix->id }}"
                    @selected(
                        (string) old('prefix_id', $irrObject->prefix_id)
                        === (string) $prefix->id
                    )
                >
                    IPv{{ $prefix->ip_version }}
                    — {{ $prefix->prefix }}
                    — {{ $prefix->client->displayName() }}
                </option>
            @endforeach
        </select>

        <div class="field-help">
            Route exige IPv4; route6 exige IPv6. O prefixo e o ASN devem
            pertencer ao mesmo cliente.
        </div>

        @error('prefix_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="source">Fonte <span>*</span></label>

        <input
            id="source"
            class="form-control"
            name="source"
            type="text"
            value="{{ old('source', $irrObject->source ?: 'LOCAL') }}"
            maxlength="50"
            required
        >

        @error('source')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="maintainer">Maintainer</label>

        <input
            id="maintainer"
            class="form-control table-mono"
            name="maintainer"
            type="text"
            value="{{ old('maintainer', $irrObject->maintainer) }}"
            maxlength="100"
            placeholder="MAINT-EXAMPLE"
        >

        @error('maintainer')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="status">Status <span>*</span></label>

        <select id="status" class="form-control" name="status" required>
            @foreach ([
                'active' => 'Ativo',
                'inactive' => 'Inativo',
                'pending' => 'Pendente',
                'deprecated' => 'Descontinuado',
                'error' => 'Erro',
            ] as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(old('status', $irrObject->status) === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        @error('status')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="last_synced_at">Última sincronização</label>

        <input
            id="last_synced_at"
            class="form-control"
            name="last_synced_at"
            type="datetime-local"
            value="{{ old(
                'last_synced_at',
                $irrObject->last_synced_at?->format('Y-m-d\TH:i')
            ) }}"
        >

        @error('last_synced_at')
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
            value="{{ old('description', $irrObject->description) }}"
            maxlength="255"
        >

        @error('description')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="raw_text">Objeto bruto</label>

        <textarea
            id="raw_text"
            class="form-control code-textarea"
            name="raw_text"
            rows="12"
            maxlength="100000"
            placeholder="route: 192.0.2.0/24&#10;origin: AS264001&#10;mnt-by: MAINT-EXAMPLE&#10;source: LOCAL"
        >{{ old('raw_text', $irrObject->raw_text) }}</textarea>

        @error('raw_text')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2 field-checkbox-group">
        <label class="checkbox-label">
            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $irrObject->active ?? true))
            >

            <span>
                <strong>Objeto ativo</strong>
                <small>Disponível nas consultas e validações do IRCENTER</small>
            </span>
        </label>
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

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (() => {
        const typeField = document.getElementById('object_type');
        const keyField = document.getElementById('object_key');
        const asnField = document.getElementById('autonomous_system_id');
        const prefixField = document.getElementById('prefix_id');
        const asnGroup = document.querySelector('[data-irr-field="asn"]');
        const prefixGroup = document.querySelector('[data-irr-field="prefix"]');

        if (! typeField || ! asnField || ! prefixField) {
            return;
        }

        const refresh = () => {
            const type = typeField.value;

            const showAsn = ['aut-num', 'as-set', 'route', 'route6']
                .includes(type);

            const showPrefix = ['route', 'route6'].includes(type);

            asnGroup.hidden = ! showAsn;
            prefixGroup.hidden = ! showPrefix;

            asnField.disabled = ! showAsn;
            prefixField.disabled = ! showPrefix;

            if (! showAsn) {
                asnField.value = '';
            }

            if (! showPrefix) {
                prefixField.value = '';
            }
        };

        typeField.addEventListener('change', () => {
            refresh();

            if (typeField.value === 'mntner' && keyField.value === '') {
                keyField.placeholder = 'MAINT-EXAMPLE';
            }

            if (typeField.value === 'as-set' && keyField.value === '') {
                keyField.placeholder = 'AS-EXAMPLE';
            }
        });

        refresh();
    })();
</script>
