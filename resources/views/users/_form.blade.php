@csrf

@if ($managedUser->exists)
    @method('PUT')
@endif

<div class="form-grid">
    <div class="field-group">
        <label for="name">Nome <span>*</span></label>

        <input
            id="name"
            class="form-control"
            name="name"
            type="text"
            value="{{ old('name', $managedUser->name) }}"
            maxlength="255"
            required
        >

        @error('name')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="email">E-mail <span>*</span></label>

        <input
            id="email"
            class="form-control"
            name="email"
            type="email"
            value="{{ old('email', $managedUser->email) }}"
            maxlength="255"
            required
        >

        @error('email')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    @if ($managedUser->exists)
        <div class="field-group">
            <label for="password">Nova senha (opcional)</label>

            <input
                id="password"
                class="form-control"
                name="password"
                type="password"
                minlength="10"
                autocomplete="new-password"
            >

            <small class="field-hint">
                Deixe em branco para manter a senha atual.
            </small>

            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="field-group field-checkbox-group">
        <label class="checkbox-label">
            <input type="hidden" name="active" value="0">

            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $managedUser->active ?? true))
            >

            <span>
                <strong>Usuário ativo</strong>
                <small>Pode autenticar na plataforma</small>
            </span>
        </label>

        @error('active')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="role">Perfil <span>*</span></label>

        <select
            id="role"
            class="form-control"
            name="role"
            required
        >
            @foreach ($roles as $role)
                <option
                    value="{{ $role }}"
                    @selected(old('role', $managedUser->role) === $role)
                >
                    {{ match ($role) {
                        'admin' => 'Administrador',
                        'operator' => 'Operador',
                        default => 'Somente leitura',
                    } }}
                </option>
            @endforeach
        </select>

        @error('role')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="client_id">Cliente vinculado</label>

        <select id="client_id" class="form-control" name="client_id">
            <option value="">Nenhum (acesso irrestrito)</option>

            @foreach ($clients as $clientOption)
                <option
                    value="{{ $clientOption->id }}"
                    @selected((string) old('client_id', $managedUser->client_id) === (string) $clientOption->id)
                >
                    {{ $clientOption->displayName() }}
                </option>
            @endforeach
        </select>

        <small class="field-hint">
            Quando preenchido, este usuário só verá dados do cliente
            selecionado (faturas, contratos, incidentes, prefixos, etc.).
        </small>

        @error('client_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-checkbox-group">
        <label class="checkbox-label">
            <input
                type="hidden"
                name="must_change_password"
                value="0"
            >

            <input
                name="must_change_password"
                type="checkbox"
                value="1"
                @checked(old(
                    'must_change_password',
                    $managedUser->must_change_password ?? true
                ))
            >

            <span>
                <strong>Exigir troca de senha</strong>
                <small>
                    O usuário deverá definir uma nova senha
                    após entrar
                </small>
            </span>
        </label>
    </div>

    @unless ($managedUser->exists)
        <div class="field-group">
            <label for="password">Senha inicial <span>*</span></label>

            <input
                id="password"
                class="form-control"
                name="password"
                type="password"
                minlength="10"
                required
                autocomplete="new-password"
            >

            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field-group">
            <label for="password_confirmation">
                Confirmar senha <span>*</span>
            </label>

            <input
                id="password_confirmation"
                class="form-control"
                name="password_confirmation"
                type="password"
                minlength="10"
                required
                autocomplete="new-password"
            >
        </div>
    @endunless
</div>

<div class="form-actions">
    <a class="button button-secondary" href="{{ route('users.index') }}">
        Cancelar
    </a>

    <button class="button button-primary" type="submit">
        {{ $managedUser->exists ? 'Salvar usuário' : 'Criar usuário' }}
    </button>
</div>
