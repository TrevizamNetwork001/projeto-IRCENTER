@props([
    'name',
    'size' => 20,
])

<svg
    {{ $attributes->merge([
        'class' => 'ui-icon',
        'width' => $size,
        'height' => $size,
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.8',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
    ]) }}
>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="2"/>
            <rect x="14" y="3" width="7" height="7" rx="2"/>
            <rect x="3" y="14" width="7" height="7" rx="2"/>
            <rect x="14" y="14" width="7" height="7" rx="2"/>
            @break

        @case('clients')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            @break

        @case('asn')
            <circle cx="12" cy="12" r="3"/>
            <circle cx="5" cy="5" r="2"/>
            <circle cx="19" cy="5" r="2"/>
            <circle cx="5" cy="19" r="2"/>
            <circle cx="19" cy="19" r="2"/>
            <path d="m7 7 3 3"/>
            <path d="m17 7-3 3"/>
            <path d="m7 17 3-3"/>
            <path d="m17 17-3-3"/>
            @break

        @case('ipv4')
            <path d="M4 6h16"/>
            <path d="M4 12h16"/>
            <path d="M4 18h16"/>
            <circle cx="7" cy="6" r="1"/>
            <circle cx="12" cy="12" r="1"/>
            <circle cx="17" cy="18" r="1"/>
            @break

        @case('ipv6')
            <path d="M5 5h14v14H5z"/>
            <path d="M5 10h14"/>
            <path d="M10 5v14"/>
            <path d="M15 5v14"/>
            <path d="M5 15h14"/>
            @break

        @case('incident')
            <path d="M12 3 2.8 19a1.4 1.4 0 0 0 1.2 2h16a1.4 1.4 0 0 0 1.2-2L12 3z"/>
            <path d="M12 9v5"/>
            <circle cx="12" cy="17.5" r=".7" fill="currentColor" stroke="none"/>
            @break

        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
            <path d="m9 12 2 2 4-4"/>
            @break

        @case('activity')
            <path d="M3 12h4l2-7 4 14 2-7h6"/>
            @break

        @case('database')
            <ellipse cx="12" cy="5" rx="8" ry="3"/>
            <path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
            <path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>
            @break

        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            @break

        @case('search')
            <circle cx="11" cy="11" r="7"/>
            <path d="m20 20-4-4"/>
            @break

        @case('logout')
            <path d="M10 17l5-5-5-5"/>
            <path d="M15 12H3"/>
            <path d="M21 19V5a2 2 0 0 0-2-2h-6"/>
            @break

        @case('chevron-right')
            <path d="m9 18 6-6-6-6"/>
            @break

        @case('workflow')
            <circle cx="6" cy="6" r="2"/>
            <circle cx="18" cy="6" r="2"/>
            <circle cx="12" cy="18" r="2"/>
            <path d="M8 6h8"/>
            <path d="m7.5 8 3.5 8"/>
            <path d="m16.5 8-3.5 8"/>
            @break

        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="m3 7 9 6 9-6"/>
            @break

        @case('eye')
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12"/>
            <circle cx="12" cy="12" r="3"/>
            @break

        @case('eye-off')
            <path d="m3 3 18 18"/>
            <path d="M10.6 6.2A10.5 10.5 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8"/>
            <path d="M6.6 6.6C3.7 8.3 2 12 2 12s3.5 6 10 6a10 10 0 0 0 5.4-1.6"/>
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
            @break

        @case('lock')
            <rect x="5" y="10" width="14" height="10" rx="2"/>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            @break

        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break

        @case('report')
            <path d="M6 3h9l3 3v15H6z"/>
            <path d="M14 3v4h4"/>
            <path d="M9 17v-4"/>
            <path d="M12 17V9"/>
            <path d="M15 17v-6"/>
            @break

        @case('registry')
            <path d="M4 4h16v5H4z"/>
            <path d="M4 15h16v5H4z"/>
            <path d="M8 9v6"/>
            <path d="M16 9v6"/>
            <circle cx="7" cy="6.5" r=".5"/>
            <circle cx="7" cy="17.5" r=".5"/>
            @break

        @case('certificate')
            <circle cx="12" cy="9" r="5"/>
            <path d="m9 13-2 8 5-3 5 3-2-8"/>
            <path d="m10 9 1.3 1.3L14 7.7"/>
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2"/>
            <path d="M12 20v2"/>
            <path d="m4.93 4.93 1.42 1.42"/>
            <path d="m17.66 17.66 1.41 1.41"/>
            <path d="M2 12h2"/>
            <path d="M20 12h2"/>
            <path d="m6.34 17.66-1.41 1.41"/>
            <path d="m19.07 4.93-1.41 1.41"/>
            @break

        @case('moon')
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79"/>
            @break

        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
