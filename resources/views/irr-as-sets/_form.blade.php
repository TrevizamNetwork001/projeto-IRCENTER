@csrf

<div class="form-grid">
    <div class="field-group">
        <label for="irr_maintainer_id">Maintainer <span>*</span></label>

        <select id="irr_maintainer_id" class="form-control" name="irr_maintainer_id" required>
            <option value="">Selecione um maintainer</option>

            @foreach ($maintainers as $item)
                <option
                    value="{{ $item->id }}"
                    @selected((string) old('irr_maintainer_id', $asSet->irr_maintainer_id) === (string) $item->id)
                >
                    {{ $item->mntner }} — {{ $item->formattedAsn() }}
                </option>
            @endforeach
        </select>

        @error('irr_maintainer_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="name">Nome do AS-set <span>*</span></label>

        <input
            id="name"
            class="form-control table-mono"
            name="name"
            type="text"
            value="{{ old('name', $asSet->name) }}"
            maxlength="100"
            placeholder="AS64500:AS-CLIENTES"
            required
        >

        @error('name')
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
            value="{{ old('descr', $asSet->descr) }}"
            maxlength="255"
        >

        @error('descr')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="members_text">Membros <span>*</span></label>

        <textarea
            id="members_text"
            class="form-control code-textarea"
            rows="6"
            placeholder="Um ASN ou as-set por linha, ex.:&#10;AS64501&#10;AS64500:AS-EDGE"
        >{{ implode("\n", (array) old('members', $asSet->members ?? [])) }}</textarea>

        @error('members')
            <div class="field-error">{{ $message }}</div>
        @enderror

        @error('members.*')
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
        const textarea = document.getElementById('members_text');
        const form = textarea?.closest('form');

        if (! form || ! textarea) {
            return;
        }

        form.addEventListener('submit', () => {
            textarea.value
                .split('\n')
                .map((value) => value.trim())
                .filter((value) => value !== '')
                .forEach((member) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'members[]';
                    input.value = member;
                    form.appendChild(input);
                });
        });
    })();
</script>
