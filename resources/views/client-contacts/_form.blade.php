@csrf

<div class="form-grid">
    <div class="form-field">
        <label for="type">Tipo</label>
        <select id="type" name="type" required>
            <option value="">Selecione</option>
            @foreach (\App\Models\ClientContact::TYPE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $contact->type) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="name">Nome</label>
        <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $contact->name) }}">
        @error('name') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" maxlength="255" required value="{{ old('email', $contact->email) }}">
        @error('email') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="phone">Telefone</label>
        <input id="phone" name="phone" type="tel" maxlength="50" value="{{ old('phone', $contact->formattedPhone()) !== '—' ? old('phone', $contact->formattedPhone()) : '' }}" placeholder="(11) 99999-9999">
        @error('phone') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <input type="hidden" name="active" value="0">
        <label class="checkbox-label" for="active">
            <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $contact->active))>
            <span><strong>Ativo</strong><small>Disponível para uso operacional.</small></span>
        </label>
    </div>

    <div class="form-field">
        <input type="hidden" name="is_primary" value="0">
        <label class="checkbox-label" for="is_primary">
            <input id="is_primary" name="is_primary" type="checkbox" value="1" @checked(old('is_primary', $contact->is_primary))>
            <span><strong>Principal</strong><small>Um principal ativo por tipo.</small></span>
        </label>
    </div>
</div>

<div class="form-actions">
    <a class="button button-secondary" href="{{ $cancelUrl }}">Cancelar</a>
    <button class="button" type="submit">{{ $submitLabel }}</button>
</div>
