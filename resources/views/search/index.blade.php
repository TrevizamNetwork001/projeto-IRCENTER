@extends('layouts.app')

@section('title', 'Busca — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">
                <span class="status-dot"></span>
                Pesquisa global
            </div>

            <h1>Buscar recursos</h1>

            <p>
                Localize clientes, ASNs, prefixos, objetos IRR e incidentes.
            </p>
        </div>
    </section>

    @if ($query === '')
        <section class="panel">
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="search" size="25"/>
                </div>

                <div>
                    <strong>Digite o que deseja encontrar</strong>
                    <span>Use o campo acima ou pressione Ctrl/⌘ + K.</span>
                </div>
            </div>
        </section>
    @elseif ($totalResults === 0)
        <section class="panel">
            <div class="empty-state empty-state-large">
                <div class="empty-state-icon">
                    <x-icon name="search" size="25"/>
                </div>

                <div>
                    <strong>Nenhum recurso encontrado</strong>
                    <span>Não há resultados para “{{ $query }}”.</span>
                </div>
            </div>
        </section>
    @else
        <p class="search-result-summary">
            {{ $totalResults }} {{ $totalResults === 1 ? 'resultado' : 'resultados' }}
            para “{{ $query }}”
        </p>

        <div class="search-result-grid">
            @foreach ([
                ['key' => 'pages', 'title' => 'Páginas e módulos', 'icon' => 'dashboard'],
                ['key' => 'clients', 'title' => 'Clientes', 'icon' => 'clients'],
                ['key' => 'autonomousSystems', 'title' => 'Sistemas autônomos', 'icon' => 'asn'],
                ['key' => 'prefixes', 'title' => 'Prefixos', 'icon' => 'ipv4'],
                ['key' => 'irrObjects', 'title' => 'Objetos IRR', 'icon' => 'registry'],
                ['key' => 'incidents', 'title' => 'Incidentes', 'icon' => 'incident'],
            ] as $group)
                @if ($results[$group['key']]->isNotEmpty())
                    <section class="panel search-result-panel">
                        <header class="search-result-header">
                            <span><x-icon :name="$group['icon']" size="19"/></span>
                            <h2>{{ $group['title'] }}</h2>
                            <span>{{ $results[$group['key']]->count() }}</span>
                        </header>

                        <div class="search-result-list">
                            @foreach ($results[$group['key']] as $resource)
                                @php
                                    [$url, $title, $detail] = match ($group['key']) {
                                        'pages' => [route($resource['route']), $resource['title'], $resource['detail']],
                                        'clients' => [route('clients.show', $resource), $resource->displayName(), collect([$resource->client_code, $resource->legal_name])->filter()->implode(' · ')],
                                        'autonomousSystems' => [route('autonomous-systems.show', $resource), $resource->formattedAsn().' · '.$resource->name, $resource->client?->displayName()],
                                        'prefixes' => [route('prefixes.show', $resource), $resource->prefix, collect([$resource->autonomousSystem?->formattedAsn(), $resource->client?->displayName(), $resource->description])->filter()->implode(' · ')],
                                        'irrObjects' => [route('irr-objects.show', $resource), $resource->object_key, collect([$resource->displayType(), $resource->source, $resource->client?->displayName()])->filter()->implode(' · ')],
                                        default => [route('routing-incidents.show', $resource), $resource->reference.' · '.$resource->title, collect([$resource->severityLabel(), $resource->statusLabel(), $resource->client?->displayName()])->filter()->implode(' · ')],
                                    };
                                @endphp

                                <a class="search-result-item" href="{{ $url }}">
                                    <strong>{{ $title }}</strong>
                                    <span>{{ $detail ?: 'Sem informações adicionais' }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>
    @endif
@endsection
