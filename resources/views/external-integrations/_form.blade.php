@csrf

<div class="form-grid">
    <div class="field-group field-span-2">
        <label for="name">Nome <span>*</span></label>

        <input
            id="name"
            class="form-control"
            name="name"
            type="text"
            maxlength="255"
            value="{{ old('name', $integration->name) }}"
            placeholder="Ex.: Monitoramento BGP externo"
            required
        >

        @error('name')
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
            @foreach (\App\Models\ExternalIntegration::types() as $value)
                <option
                    value="{{ $value }}"
                    @selected(old('type', $integration->type) === $value)
                >
                    {{ (new \App\Models\ExternalIntegration([
                        'type' => $value,
                    ]))->typeLabel() }}
                </option>
            @endforeach
        </select>

        @error('type')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="timeout_seconds">
            Timeout em segundos <span>*</span>
        </label>

        <input
            id="timeout_seconds"
            class="form-control"
            name="timeout_seconds"
            type="number"
            min="2"
            max="30"
            value="{{ old(
                'timeout_seconds',
                $integration->timeout_seconds ?? 10
            ) }}"
            required
        >

        @error('timeout_seconds')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="endpoint">Endpoint HTTPS <span>*</span></label>

        <input
            id="endpoint"
            class="form-control table-mono"
            name="endpoint"
            type="url"
            maxlength="2048"
            value="{{ old('endpoint', $integration->endpoint) }}"
            placeholder="https://api.exemplo.net/status"
            required
        >

        <div class="field-help">
            Somente HTTPS na porta 443. Endereços locais, privados e
            reservados são bloqueados.
        </div>

        @error('endpoint')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="authentication_type">
            Autenticação <span>*</span>
        </label>

        <select
            id="authentication_type"
            class="form-control"
            name="authentication_type"
            required
        >
            @foreach (
                \App\Models\ExternalIntegration::authenticationTypes()
                as $value
            )
                <option
                    value="{{ $value }}"
                    @selected(
                        old(
                            'authentication_type',
                            $integration->authentication_type
                        ) === $value
                    )
                >
                    {{ (new \App\Models\ExternalIntegration([
                        'authentication_type' => $value,
                    ]))->authenticationLabel() }}
                </option>
            @endforeach
        </select>

        @error('authentication_type')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="username">Usuário</label>

        <input
            id="username"
            class="form-control"
            name="username"
            type="text"
            maxlength="255"
            autocomplete="off"
            value="{{ old('username', $integration->username) }}"
            placeholder="Usado apenas na autenticação básica"
        >

        @error('username')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="secret">
            {{ $integration->exists
                ? 'Novo segredo'
                : 'Segredo' }}
        </label>

        <input
            id="secret"
            class="form-control"
            name="secret"
            type="password"
            maxlength="10000"
            autocomplete="new-password"
            value=""
            placeholder="{{ $integration->exists
                ? 'Deixe vazio para preservar o segredo atual'
                : 'Token ou senha da integração' }}"
        >

        <div class="field-help">
            O valor é criptografado no banco e nunca será exibido novamente.
        </div>

        @error('secret')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2 field-checkbox-group">
        <label class="checkbox-label">
            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $integration->active ?? true))
            >

            <span>
                Integração ativa
                <small>
                    Integrações desativadas permanecem cadastradas, mas
                    não devem participar de sincronizações futuras.
                </small>
            </span>
        </label>

        @error('active')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="integration-security-notice">
    <x-icon name="shield" size="20"/>

    <div>
        <strong>Proteções desta etapa</strong>

        <span>
            Sem redirecionamentos, sem armazenamento do corpo da resposta,
            TLS obrigatório e bloqueio de redes internas.
        </span>
    </div>
</div>

<div class="form-actions">
    <a
        class="button button-ghost"
        href="{{ $integration->exists
            ? route('external-integrations.show', $integration)
            : route('external-integrations.index') }}"
    >
        Cancelar
    </a>

    <button class="button button-primary" type="submit">
        {{ $integration->exists
            ? 'Salvar alterações'
            : 'Cadastrar integração' }}
    </button>
</div>
