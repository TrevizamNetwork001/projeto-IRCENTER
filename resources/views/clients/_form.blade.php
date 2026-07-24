@csrf

<div class="form-grid">
    <div class="field-group field-span-2">
        <label for="legal_name">Razão social <span>*</span></label>
        <input
            id="legal_name"
            class="form-control"
            name="legal_name"
            type="text"
            value="{{ old('legal_name', $client->legal_name) }}"
            maxlength="255"
            required
        >
        @error('legal_name')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="trade_name">Nome fantasia</label>
        <input
            id="trade_name"
            class="form-control"
            name="trade_name"
            type="text"
            value="{{ old('trade_name', $client->trade_name) }}"
            maxlength="255"
        >
        @error('trade_name')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="document">CPF ou CNPJ</label>
        <input
            id="document"
            class="form-control"
            name="document"
            type="text"
            value="{{ old('document', $client->formattedDocument()) !== '—'
                ? old('document', $client->formattedDocument())
                : '' }}"
            maxlength="18"
            inputmode="numeric"
            placeholder="000.000.000-00 ou 00.000.000/0000-00"
        >
        @error('document')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="contract_number">Número do contrato</label>
        <input
            id="contract_number"
            class="form-control"
            name="contract_number"
            type="text"
            value="{{ old('contract_number', $client->contract_number) }}"
            maxlength="50"
            placeholder="Ex.: 2026-00045"
        >
        <small class="field-help">
            Opcional. Pode ser informado manualmente ou gerado pelo sistema.
        </small>
        @error('contract_number')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-checkbox-group">
        <label class="checkbox-label">
            <input
                id="generate_contract_number"
                name="generate_contract_number"
                type="checkbox"
                value="1"
                @checked(old('generate_contract_number'))
            >
            <span>
                <strong>Gerar número automaticamente</strong>
                <small>Formato CTR-ANO-SEQUENCIAL</small>
            </span>
        </label>
        @error('generate_contract_number')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="email">E-mail</label>
        <input
            id="email"
            class="form-control"
            name="email"
            type="email"
            value="{{ old('email', $client->email) }}"
            maxlength="255"
            placeholder="contato@empresa.com.br"
        >
        @error('email')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="phone">Telefone</label>
        <input
            id="phone"
            class="form-control"
            name="phone"
            type="text"
            value="{{ old('phone', $client->formattedPhone()) !== '—'
                ? old('phone', $client->formattedPhone())
                : '' }}"
            maxlength="19"
            inputmode="tel"
            placeholder="(11) 99999-9999"
        >
        @error('phone')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-span-2">
        <label for="website">Site</label>
        <input
            id="website"
            class="form-control"
            name="website"
            type="text"
            value="{{ old('website', $client->website) }}"
            maxlength="255"
            placeholder="empresa.com.br ou https://empresa.com.br"
        >
        @error('website')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="postal_code">CEP</label>
        <input
            id="postal_code"
            class="form-control"
            name="postal_code"
            type="text"
            value="{{ old('postal_code', $client->formattedPostalCode()) !== '—'
                ? old('postal_code', $client->formattedPostalCode())
                : '' }}"
            maxlength="9"
            inputmode="numeric"
            autocomplete="postal-code"
            placeholder="00000-000"
        >
        <small id="postal-code-feedback" class="field-help">
            Ao informar um CEP válido, o endereço será preenchido automaticamente.
        </small>
        @error('postal_code')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="street">Logradouro</label>
        <input
            id="street"
            class="form-control"
            name="street"
            type="text"
            value="{{ old('street', $client->street) }}"
            maxlength="255"
            autocomplete="address-line1"
        >
        @error('street')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="address_number">Número</label>
        <input
            id="address_number"
            class="form-control"
            name="address_number"
            type="text"
            value="{{ old('address_number', $client->address_number) }}"
            maxlength="30"
            placeholder="Ex.: 123 ou S/N"
        >
        @error('address_number')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="address_complement">Complemento</label>
        <input
            id="address_complement"
            class="form-control"
            name="address_complement"
            type="text"
            value="{{ old('address_complement', $client->address_complement) }}"
            maxlength="100"
            placeholder="Sala, bloco, andar..."
            autocomplete="address-line2"
        >
        @error('address_complement')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="district">Bairro</label>
        <input
            id="district"
            class="form-control"
            name="district"
            type="text"
            value="{{ old('district', $client->district) }}"
            maxlength="100"
        >
        @error('district')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="city">Cidade</label>
        <input
            id="city"
            class="form-control"
            name="city"
            type="text"
            value="{{ old('city', $client->city) }}"
            maxlength="100"
            autocomplete="address-level2"
        >
        @error('city')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group">
        <label for="state">Estado</label>
        <input
            id="state"
            class="form-control"
            name="state"
            type="text"
            value="{{ old('state', $client->state) }}"
            maxlength="100"
            autocomplete="address-level1"
        >
        @error('state')
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
            value="{{ old('country', $client->country ?: 'BR') }}"
            minlength="2"
            maxlength="2"
            required
            autocomplete="country"
        >
        @error('country')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="field-group field-checkbox-group">
        <label class="checkbox-label">
            <input
                name="active"
                type="checkbox"
                value="1"
                @checked(old('active', $client->active ?? true))
            >
            <span>
                <strong>Cliente ativo</strong>
                <small>Disponível para vínculos e operações</small>
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
        >{{ old('notes', $client->notes) }}</textarea>
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

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const digits = value => value.replace(/\D/g, '');

            const documentInput = document.getElementById('document');
            const phoneInput = document.getElementById('phone');
            const postalCodeInput = document.getElementById('postal_code');
            const contractInput = document.getElementById('contract_number');
            const generateContractInput = document.getElementById(
                'generate_contract_number'
            );
            const feedback = document.getElementById(
                'postal-code-feedback'
            );

            const formatDocument = value => {
                const number = digits(value).slice(0, 14);

                if (number.length <= 11) {
                    return number
                        .replace(/^(\d{3})(\d)/, '$1.$2')
                        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                        .replace(/\.(\d{3})(\d)/, '.$1-$2');
                }

                return number
                    .replace(/^(\d{2})(\d)/, '$1.$2')
                    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d)/, '.$1/$2')
                    .replace(/(\d{4})(\d)/, '$1-$2');
            };

            const formatPhone = value => {
                let number = digits(value);

                if (
                    number.startsWith('55')
                    && number.length > 11
                ) {
                    number = number.slice(2);
                }

                number = number.slice(0, 11);

                if (number.length <= 10) {
                    return number
                        .replace(/^(\d{2})(\d)/, '($1) $2')
                        .replace(/(\d{4})(\d)/, '$1-$2');
                }

                return number
                    .replace(/^(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{5})(\d)/, '$1-$2');
            };

            const formatPostalCode = value => {
                return digits(value)
                    .slice(0, 8)
                    .replace(/^(\d{5})(\d)/, '$1-$2');
            };

            documentInput?.addEventListener('input', event => {
                event.target.value = formatDocument(event.target.value);
            });

            phoneInput?.addEventListener('input', event => {
                event.target.value = formatPhone(event.target.value);
            });

            const updateContractState = () => {
                if (! contractInput || ! generateContractInput) {
                    return;
                }

                contractInput.disabled = generateContractInput.checked;
                contractInput.classList.toggle(
                    'is-disabled',
                    generateContractInput.checked
                );

                if (generateContractInput.checked) {
                    contractInput.dataset.previousValue =
                        contractInput.value;
                    contractInput.value = '';
                    contractInput.placeholder =
                        'Será gerado ao salvar';
                } else {
                    contractInput.value =
                        contractInput.dataset.previousValue || '';
                    contractInput.placeholder = 'Ex.: 2026-00045';
                }
            };

            generateContractInput?.addEventListener(
                'change',
                updateContractState
            );

            updateContractState();

            let lastRequestedPostalCode = '';

            const lookupPostalCode = async () => {
                if (! postalCodeInput) {
                    return;
                }

                const postalCode = digits(postalCodeInput.value);

                if (
                    postalCode.length !== 8
                    || postalCode === lastRequestedPostalCode
                ) {
                    return;
                }

                lastRequestedPostalCode = postalCode;

                if (feedback) {
                    feedback.textContent = 'Consultando CEP...';
                }

                try {
                    const response = await fetch(
                        `https://viacep.com.br/ws/${postalCode}/json/`,
                        {
                            headers: {
                                Accept: 'application/json',
                            },
                        }
                    );

                    if (! response.ok) {
                        throw new Error('Falha ao consultar CEP.');
                    }

                    const address = await response.json();

                    if (address.erro) {
                        if (feedback) {
                            feedback.textContent =
                                'CEP não encontrado. Preencha o endereço manualmente.';
                        }

                        return;
                    }

                    const fields = {
                        street: address.logradouro,
                        district: address.bairro,
                        city: address.localidade,
                        state: address.uf,
                    };

                    Object.entries(fields).forEach(([id, value]) => {
                        const input = document.getElementById(id);

                        if (input && value) {
                            input.value = value;
                        }
                    });

                    const numberInput = document.getElementById(
                        'address_number'
                    );

                    numberInput?.focus();

                    if (feedback) {
                        feedback.textContent =
                            'Endereço preenchido. Informe o número e revise os dados.';
                    }
                } catch (error) {
                    if (feedback) {
                        feedback.textContent =
                            'Não foi possível consultar o CEP. Preencha o endereço manualmente.';
                    }
                }
            };

            postalCodeInput?.addEventListener('input', event => {
                event.target.value = formatPostalCode(
                    event.target.value
                );

                if (digits(event.target.value).length === 8) {
                    lookupPostalCode();
                }
            });

            postalCodeInput?.addEventListener(
                'blur',
                lookupPostalCode
            );
        });
    </script>
@endonce
