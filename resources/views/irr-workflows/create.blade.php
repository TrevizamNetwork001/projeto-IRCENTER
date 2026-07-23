@extends('layouts.app')

@section('title', 'Iniciar implantação IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Assistente IRR</div>

            <h1>Iniciar implantação IRR</h1>

            <p>
                Use os dados já cadastrados no IRCENTER e complete somente
                as informações necessárias para os envios.
            </p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-workflows.store') }}">
            @csrf

            <div class="form-grid">
                <div class="field-group">
                    <label for="client_id">
                        Cliente <span>*</span>
                    </label>

                    <select
                        id="client_id"
                        class="form-control"
                        name="client_id"
                        required
                    >
                        <option value="">Selecione</option>

                        @foreach ($clients as $client)
                            <option
                                value="{{ $client->id }}"
                                @selected(
                                    (string) old('client_id')
                                    === (string) $client->id
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
                    <label for="autonomous_system_id">
                        ASN <span>*</span>
                    </label>

                    <select
                        id="autonomous_system_id"
                        class="form-control"
                        name="autonomous_system_id"
                        required
                    >
                        <option value="">Selecione</option>

                        @foreach ($autonomousSystems as $autonomousSystem)
                            <option
                                value="{{ $autonomousSystem->id }}"
                                data-client="{{ $autonomousSystem->client_id }}"
                                data-asn="{{ $autonomousSystem->formattedAsn() }}"
                                data-name="{{ $autonomousSystem->name }}"
                                @selected(
                                    (string) old('autonomous_system_id')
                                    === (string) $autonomousSystem->id
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

                <div class="field-group field-span-2">
                    <label for="name">
                        Nome do processo <span>*</span>
                    </label>

                    <input
                        id="name"
                        class="form-control"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        maxlength="255"
                        placeholder="Implantação IRR AS65000"
                        required
                    >

                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="profile_key">
                        Perfil da base IRR <span>*</span>
                    </label>

                    <select
                        id="profile_key"
                        class="form-control"
                        name="profile_key"
                        required
                    >
                        @foreach ($irrProfiles as $key => $profile)
                            <option
                                value="{{ $key }}"
                                data-source="{{ $profile['source'] }}"
                                data-email="{{ $profile['destination_email'] }}"
                                data-subject="{{ $profile['subject'] }}"
                                @selected(
                                    old('profile_key', 'tc') === $key
                                )
                            >
                                {{ $profile['label'] }}
                            </option>
                        @endforeach
                    </select>

                    <div class="field-help">
                        O perfil define fonte, destinatário e sugestões
                        de tamanho para IPv4 e IPv6.
                    </div>

                    @error('profile_key')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="irr_source">
                        Fonte IRR <span>*</span>
                    </label>

                    <input
                        id="irr_source"
                        class="form-control"
                        name="irr_source"
                        type="text"
                        value="{{ old('irr_source', 'TC') }}"
                        maxlength="50"
                        required
                    >

                    @error('irr_source')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="destination_email">
                        E-mail de destino
                    </label>

                    <input
                        id="destination_email"
                        class="form-control"
                        name="destination_email"
                        type="email"
                        value="{{ old('destination_email') }}"
                        maxlength="255"
                        placeholder="E-mail informado pela base IRR"
                    >

                    @error('destination_email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="maintainer">
                        Maintainer <span>*</span>
                    </label>

                    <input
                        id="maintainer"
                        class="form-control table-mono"
                        name="maintainer"
                        type="text"
                        value="{{ old('maintainer') }}"
                        maxlength="100"
                        placeholder="MAINT-AS65000"
                        required
                    >

                    @error('maintainer')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="contact_handle">
                        Identificador do contato <span>*</span>
                    </label>

                    <input
                        id="contact_handle"
                        class="form-control table-mono"
                        name="contact_handle"
                        type="text"
                        value="{{ old('contact_handle') }}"
                        maxlength="100"
                        placeholder="CONTATO-EXAMPLE"
                        required
                    >

                    @error('contact_handle')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="route_set">
                        Route-set <span>*</span>
                    </label>

                    <input
                        id="route_set"
                        class="form-control table-mono"
                        name="route_set"
                        type="text"
                        value="{{ old('route_set') }}"
                        maxlength="100"
                        placeholder="AS65000:RS-ROUTES"
                        required
                    >

                    @error('route_set')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="as_set">
                        AS-set <span>*</span>
                    </label>

                    <input
                        id="as_set"
                        class="form-control table-mono"
                        name="as_set"
                        type="text"
                        value="{{ old('as_set') }}"
                        maxlength="100"
                        placeholder="AS65000:AS-ALL"
                        required
                    >

                    @error('as_set')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="contact_name">
                        Nome do contato <span>*</span>
                    </label>

                    <input
                        id="contact_name"
                        class="form-control"
                        name="contact_name"
                        type="text"
                        value="{{ old('contact_name') }}"
                        maxlength="255"
                        required
                    >

                    @error('contact_name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="contact_email">
                        E-mail do contato <span>*</span>
                    </label>

                    <input
                        id="contact_email"
                        class="form-control"
                        name="contact_email"
                        type="email"
                        value="{{ old('contact_email') }}"
                        maxlength="255"
                        required
                    >

                    @error('contact_email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group">
                    <label for="contact_phone">Telefone</label>

                    <input
                        id="contact_phone"
                        class="form-control"
                        name="contact_phone"
                        type="text"
                        value="{{ old('contact_phone') }}"
                        maxlength="50"
                    >

                    @error('contact_phone')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group field-span-2">
                    <label for="contact_address">Endereço</label>

                    <textarea
                        id="contact_address"
                        class="form-control"
                        name="contact_address"
                        rows="3"
                        maxlength="2000"
                    >{{ old('contact_address') }}</textarea>

                    @error('contact_address')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-group field-span-2">
                    <label for="notes">Observações internas</label>

                    <textarea
                        id="notes"
                        class="form-control"
                        name="notes"
                        rows="4"
                        maxlength="5000"
                    >{{ old('notes') }}</textarea>

                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <a
                    class="button button-secondary"
                    href="{{ route('irr-workflows.index') }}"
                >
                    Cancelar
                </a>

                <button class="button button-primary" type="submit">
                    Criar roteiro IRR
                </button>
            </div>
        </form>
    </section>

    <script>
        (() => {
            const clientField = document.getElementById('client_id');
            const asnField = document.getElementById('autonomous_system_id');
            const profileField = document.getElementById('profile_key');
            const sourceField = document.getElementById('irr_source');
            const destinationField = document.getElementById(
                'destination_email'
            );
            const nameField = document.getElementById('name');
            const maintainerField = document.getElementById('maintainer');
            const routeSetField = document.getElementById('route_set');
            const asSetField = document.getElementById('as_set');

            if (! clientField || ! asnField) {
                return;
            }

            const options = Array.from(asnField.options);

            const applyProfile = () => {
                const option = profileField?.selectedOptions[0];

                if (! option) {
                    return;
                }

                sourceField.value = option.dataset.source || 'LOCAL';

                if (
                    option.dataset.email
                    && ! destinationField.dataset.manuallyChanged
                ) {
                    destinationField.value = option.dataset.email;
                }
            };

            const filterAsns = () => {
                const clientId = clientField.value;

                options.forEach((option) => {
                    if (! option.value) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = option.dataset.client !== clientId;
                });

                const selected = asnField.selectedOptions[0];

                if (
                    selected
                    && selected.value
                    && selected.dataset.client !== clientId
                ) {
                    asnField.value = '';
                }
            };

            const suggestNames = () => {
                const option = asnField.selectedOptions[0];

                if (! option || ! option.value) {
                    return;
                }

                const asn = option.dataset.asn;

                if (! nameField.value) {
                    nameField.value = `Implantação IRR ${asn}`;
                }

                if (! maintainerField.value) {
                    maintainerField.value = `MAINT-${asn}`;
                }

                if (! routeSetField.value) {
                    routeSetField.value = `${asn}:RS-ROUTES`;
                }

                if (! asSetField.value) {
                    asSetField.value = `${asn}:AS-ALL`;
                }
            };

            clientField.addEventListener('change', filterAsns);
            asnField.addEventListener('change', suggestNames);
            profileField?.addEventListener('change', applyProfile);

            destinationField?.addEventListener('input', () => {
                destinationField.dataset.manuallyChanged = '1';
            });

            filterAsns();
            suggestNames();
            applyProfile();
        })();
    </script>
@endsection
