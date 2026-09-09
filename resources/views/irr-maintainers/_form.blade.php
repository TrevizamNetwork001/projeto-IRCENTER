@csrf

<div class="form-grid">
    <div class="field-group">
        <label for="mntner">Maintainer (mntner) <span>*</span></label>

        <input
            id="mntner"
            class="form-control table-mono"
            name="mntner"
            type="text"
            value="{{ old('mntner', $maintainer->mntner) }}"
            maxlength="100"
            placeholder="MAINT-AS64500"
            required
        >

        @error('mntner')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="asn">ASN <span>*</span></label>

        <input
            id="asn"
            class="form-control table-mono"
            name="asn"
            type="number"
            min="0"
            value="{{ old('asn', $maintainer->asn) }}"
            required
        >

        @error('asn')
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
                    @selected((string) old('client_id', $maintainer->client_id) === (string) $client->id)
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
        <label for="password">
            Senha do mntner <span>*</span>
        </label>

        <input
            id="password"
            class="form-control"
            name="password"
            type="password"
            autocomplete="new-password"
            placeholder="{{ $maintainer->exists ? 'Deixe em branco para manter a senha atual' : '' }}"
            @unless ($maintainer->exists) required @endunless
        >

        <div class="field-help">
            É a mesma senha definida no cadastro do mntner feito no
            <a href="https://bgp.net.br/wizard.html" target="_blank" rel="noopener">wizard do TC</a>.
            O IRCENTER guarda essa senha de forma criptografada e a usa
            apenas para publicar objetos em seu nome.
        </div>

        @error('password')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="admin_c">admin-c <span>*</span></label>

        <input
            id="admin_c"
            class="form-control table-mono"
            name="admin_c"
            type="text"
            value="{{ old('admin_c', $maintainer->admin_c) }}"
            maxlength="100"
            required
        >

        @error('admin_c')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="tech_c">tech-c <span>*</span></label>

        <input
            id="tech_c"
            class="form-control table-mono"
            name="tech_c"
            type="text"
            value="{{ old('tech_c', $maintainer->tech_c) }}"
            maxlength="100"
            required
        >

        @error('tech_c')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="notify_email">E-mail de notificação (mnt-nfy)</label>

        <input
            id="notify_email"
            class="form-control"
            name="notify_email"
            type="email"
            value="{{ old('notify_email', $maintainer->notify_email) }}"
            maxlength="255"
        >

        @error('notify_email')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="descr">Descrição</label>

        <input
            id="descr"
            class="form-control"
            name="descr"
            type="text"
            value="{{ old('descr', $maintainer->descr) }}"
            maxlength="255"
        >

        @error('descr')
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
