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
