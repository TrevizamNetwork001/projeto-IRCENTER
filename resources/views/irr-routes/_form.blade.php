@csrf

<div class="form-grid">
    <div class="field-group">
        <label for="irr_maintainer_id">Maintainer <span>*</span></label>

        <select id="irr_maintainer_id" class="form-control" name="irr_maintainer_id" required>
            <option value="">Selecione um maintainer</option>

            @foreach ($maintainers as $item)
                <option
                    value="{{ $item->id }}"
                    data-asn="{{ $item->asn }}"
                    @selected((string) old('irr_maintainer_id', $route->irr_maintainer_id) === (string) $item->id)
                >
                    {{ $item->mntner }} — {{ $item->formattedAsn() }}
                </option>
            @endforeach
        </select>

        <div class="field-help">
            O objeto será publicado com <code>origin</code> igual ao ASN
            deste maintainer — o TC não aceita objetos proxy.
        </div>

        @error('irr_maintainer_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="prefix">Prefixo (CIDR) <span>*</span></label>

        <input
            id="prefix"
            class="form-control table-mono"
            name="prefix"
            type="text"
            value="{{ old('prefix', $route->prefix) }}"
            maxlength="64"
            placeholder="192.0.2.0/24"
            required
        >

        @error('prefix')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="version">Versão <span>*</span></label>

        <select id="version" class="form-control" name="version" required>
            <option value="4" @selected((string) old('version', $route->version) === '4')>IPv4 (route)</option>
            <option value="6" @selected((string) old('version', $route->version) === '6')>IPv6 (route6)</option>
        </select>

        @error('version')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="origin_asn">AS de origem <span>*</span></label>

        <input
            id="origin_asn"
            class="form-control table-mono"
            name="origin_asn"
            type="number"
            min="0"
            value="{{ old('origin_asn', $route->origin_asn) }}"
            required
        >

        @error('origin_asn')
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
            value="{{ old('descr', $route->descr) }}"
            maxlength="255"
        >

        @error('descr')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="member_of_text">Member-of</label>

        <textarea
            id="member_of_text"
            class="form-control code-textarea"
            data-multiline-field
            data-name="member_of"
            rows="3"
            placeholder="Um route-set/as-set por linha, ex.: AS268359:RS-ROUTES"
        >{{ implode("\n", (array) old('member_of', $route->member_of ?? [])) }}</textarea>

        @error('member_of')
            <div class="field-error">{{ $message }}</div>
        @enderror

        @error('member_of.*')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="remarks">Remarks</label>

        <textarea
            id="remarks"
            class="form-control code-textarea"
            name="remarks"
            rows="4"
            maxlength="2000"
            placeholder="Uma observação por linha — cada linha vira um atributo remarks: separado"
        >{{ old('remarks', $route->remarks) }}</textarea>

        @error('remarks')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="notify_text">Notify</label>

        <textarea
            id="notify_text"
            class="form-control code-textarea"
            data-multiline-field
            data-name="notify"
            rows="3"
            placeholder="Um e-mail por linha"
        >{{ implode("\n", (array) old('notify', $route->notify ?? [])) }}</textarea>

        @error('notify')
            <div class="field-error">{{ $message }}</div>
        @enderror

        @error('notify.*')
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

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (() => {
        const maintainerField = document.getElementById('irr_maintainer_id');
        const originField = document.getElementById('origin_asn');

        if (! maintainerField || ! originField || originField.value !== '') {
            return;
        }

        maintainerField.addEventListener('change', () => {
            const option = maintainerField.selectedOptions[0];
            const asn = option ? option.dataset.asn : '';

            if (asn) {
                originField.value = asn;
            }
        });
    })();

    (() => {
        const form = document.querySelector('[data-multiline-field]')?.closest('form');

        if (! form) {
            return;
        }

        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-multiline-field]').forEach((textarea) => {
                textarea.value
                    .split('\n')
                    .map((value) => value.trim())
                    .filter((value) => value !== '')
                    .forEach((value) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `${textarea.dataset.name}[]`;
                        input.value = value;
                        form.appendChild(input);
                    });
            });
        });
    })();
</script>
