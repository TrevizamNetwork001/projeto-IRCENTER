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
            data-multiline-field
            data-name="members"
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

    <div class="field-group">
        <label for="admin_c">admin-c <span>*</span></label>

        <input
            id="admin_c"
            class="form-control table-mono"
            name="admin_c"
            type="text"
            value="{{ old('admin_c', $asSet->admin_c) }}"
            maxlength="100"
            placeholder="SFCLM9-NICBR"
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
            value="{{ old('tech_c', $asSet->tech_c) }}"
            maxlength="100"
            placeholder="SFCLM9-NICBR"
            required
        >

        @error('tech_c')
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
        >{{ implode("\n", (array) old('notify', $asSet->notify ?? [])) }}</textarea>

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
