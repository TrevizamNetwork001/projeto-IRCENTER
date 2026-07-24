@csrf

<div class="form-grid">
    <div class="field-group field-span-2">
        <label for="title">Título <span>*</span></label>

        <input
            id="title"
            class="form-control"
            name="title"
            type="text"
            value="{{ old('title', $incident->title) }}"
            maxlength="255"
            placeholder="Descrição curta e objetiva do incidente"
            required
        >

        @error('title')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="type">Tipo <span>*</span></label>

        <select
            id="type"
            class="form-control"
            name="type"
            required
        >
            <option value="">Selecione</option>

            @foreach (\App\Models\RoutingIncident::types() as $value)
                <option
                    value="{{ $value }}"
                    @selected(old('type', $incident->type) === $value)
                >
                    {{ (new \App\Models\RoutingIncident([
                        'type' => $value
                    ]))->typeLabel() }}
                </option>
            @endforeach
        </select>

        @error('type')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="severity">Severidade <span>*</span></label>

        <select
            id="severity"
            class="form-control"
            name="severity"
            required
        >
            @foreach (\App\Models\RoutingIncident::severities() as $value)
                <option
                    value="{{ $value }}"
                    @selected(
                        old('severity', $incident->severity) === $value
                    )
                >
                    {{ (new \App\Models\RoutingIncident([
                        'severity' => $value
                    ]))->severityLabel() }}
                </option>
            @endforeach
        </select>

        @error('severity')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="status">Status <span>*</span></label>

        <select
            id="status"
            class="form-control"
            name="status"
            required
        >
            @foreach (\App\Models\RoutingIncident::statuses() as $value)
                <option
                    value="{{ $value }}"
                    @selected(old('status', $incident->status) === $value)
                >
                    {{ (new \App\Models\RoutingIncident([
                        'status' => $value
                    ]))->statusLabel() }}
                </option>
            @endforeach
        </select>

        @error('status')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="detected_at">Detectado em <span>*</span></label>

        <input
            id="detected_at"
            class="form-control"
            name="detected_at"
            type="datetime-local"
            value="{{ old(
                'detected_at',
                $incident->detected_at
                    ?->format('Y-m-d\TH:i')
            ) }}"
            required
        >

        @error('detected_at')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="client_id">Cliente</label>

        <select
            id="client_id"
            class="form-control"
            name="client_id"
        >
            <option value="">Sem cliente vinculado</option>

            @foreach ($clients as $client)
                <option
                    value="{{ $client->id }}"
                    @selected(
                        (string) old(
                            'client_id',
                            $incident->client_id
                        ) === (string) $client->id
                    )
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
        <label for="assigned_to_user_id">Responsável</label>

        <select
            id="assigned_to_user_id"
            class="form-control"
            name="assigned_to_user_id"
        >
            <option value="">Não atribuído</option>

            @foreach ($users as $user)
                <option
                    value="{{ $user->id }}"
                    @selected(
                        (string) old(
                            'assigned_to_user_id',
                            $incident->assigned_to_user_id
                        ) === (string) $user->id
                    )
                >
                    {{ $user->name }} — {{ $user->roleLabel() }}
                </option>
            @endforeach
        </select>

        @error('assigned_to_user_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="autonomous_system_id">ASN relacionado</label>

        <select
            id="autonomous_system_id"
            class="form-control"
            name="autonomous_system_id"
        >
            <option value="">Sem ASN vinculado</option>

            @foreach ($autonomousSystems as $asn)
                <option
                    value="{{ $asn->id }}"
                    data-client="{{ $asn->client_id }}"
                    @selected(
                        (string) old(
                            'autonomous_system_id',
                            $incident->autonomous_system_id
                        ) === (string) $asn->id
                    )
                >
                    {{ $asn->formattedAsn() }}
                    — {{ $asn->name }}
                    — {{ $asn->client->displayName() }}
                </option>
            @endforeach
        </select>

        @error('autonomous_system_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="prefix_id">Prefixo relacionado</label>

        <select
            id="prefix_id"
            class="form-control"
            name="prefix_id"
        >
            <option value="">Sem prefixo vinculado</option>

            @foreach ($prefixes as $prefix)
                <option
                    value="{{ $prefix->id }}"
                    data-client="{{ $prefix->client_id }}"
                    data-asn="{{ $prefix->autonomous_system_id }}"
                    @selected(
                        (string) old(
                            'prefix_id',
                            $incident->prefix_id
                        ) === (string) $prefix->id
                    )
                >
                    {{ $prefix->prefix }}
                    — {{ $prefix->client->displayName() }}
                </option>
            @endforeach
        </select>

        @error('prefix_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="source">Origem da informação</label>

        <input
            id="source"
            class="form-control"
            name="source"
            type="text"
            value="{{ old('source', $incident->source) }}"
            maxlength="100"
            placeholder="NOC, cliente, monitoramento, RPKI..."
        >

        @error('source')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="external_reference">Referência externa</label>

        <input
            id="external_reference"
            class="form-control"
            name="external_reference"
            type="text"
            value="{{ old(
                'external_reference',
                $incident->external_reference
            ) }}"
            maxlength="255"
            placeholder="Protocolo, ticket, URL ou identificador"
        >

        @error('external_reference')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="summary">Resumo do incidente <span>*</span></label>

        <textarea
            id="summary"
            class="form-control incident-textarea"
            name="summary"
            maxlength="10000"
            required
        >{{ old('summary', $incident->summary) }}</textarea>

        @error('summary')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    @foreach ([
        'impact' => 'Impacto observado',
        'evidence' => 'Evidências técnicas',
        'mitigation' => 'Mitigação aplicada ou planejada',
        'root_cause' => 'Causa raiz',
    ] as $field => $label)
        <div class="field-group field-span-2">
            <label for="{{ $field }}">{{ $label }}</label>

            <textarea
                id="{{ $field }}"
                class="form-control incident-textarea"
                name="{{ $field }}"
                maxlength="{{ $field === 'evidence' ? 20000 : 10000 }}"
            >{{ old($field, $incident->{$field}) }}</textarea>

            @error($field)
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    @endforeach
</div>

<div class="form-actions">
    <a
        class="button button-ghost"
        href="{{ $incident->exists
            ? route('routing-incidents.show', $incident)
            : route('routing-incidents.index') }}"
    >
        Cancelar
    </a>

    <button class="button button-primary" type="submit">
        {{ $incident->exists
            ? 'Salvar alterações'
            : 'Registrar incidente' }}
    </button>
</div>
