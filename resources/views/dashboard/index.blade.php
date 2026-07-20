<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — IRCENTER</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div>
                <div class="brand">IRCENTER</div>
                <div class="brand-subtitle">Internet Resource Center</div>
            </div>

            <div class="user-area">
                <span>{{ auth()->user()->name }}</span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="secondary-button" type="submit">Sair</button>
                </form>
            </div>
        </div>
    </header>

    <main class="dashboard-main">
        <div class="eyebrow">Visão geral</div>
        <h1>Dashboard operacional</h1>

        <p class="dashboard-description">
            Base inicial para clientes, ASNs e recursos de numeração.
        </p>

        <section class="metrics-grid">
            @foreach ([
                ['label' => 'Clientes', 'value' => $metrics['clients']],
                ['label' => 'ASNs', 'value' => $metrics['asns']],
                ['label' => 'Prefixos IPv4', 'value' => $metrics['ipv4_prefixes']],
                ['label' => 'Prefixos IPv6', 'value' => $metrics['ipv6_prefixes']],
            ] as $metric)
                <article class="metric-card">
                    <div class="metric-label">{{ $metric['label'] }}</div>
                    <div class="metric-value">{{ $metric['value'] }}</div>
                </article>
            @endforeach
        </section>
    </main>
</body>
</html>
