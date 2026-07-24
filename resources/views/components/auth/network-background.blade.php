<svg
    class="auth-network-visual"
    viewBox="0 0 920 640"
    fill="none"
    aria-hidden="true"
>
    <defs>
        <linearGradient
            id="auth-route-gradient"
            x1="70"
            y1="90"
            x2="840"
            y2="560"
            gradientUnits="userSpaceOnUse"
        >
            <stop stop-color="#21c7e8" stop-opacity=".12"/>
            <stop offset=".5" stop-color="#21c7e8" stop-opacity=".72"/>
            <stop offset="1" stop-color="#9c7cff" stop-opacity=".15"/>
        </linearGradient>

        <radialGradient id="auth-node-glow">
            <stop stop-color="#21c7e8" stop-opacity=".72"/>
            <stop offset="1" stop-color="#21c7e8" stop-opacity="0"/>
        </radialGradient>

        <filter
            id="auth-glow"
            x="-100%"
            y="-100%"
            width="300%"
            height="300%"
        >
            <feGaussianBlur stdDeviation="5.5"/>
        </filter>

        <pattern
            id="auth-grid"
            width="44"
            height="44"
            patternUnits="userSpaceOnUse"
        >
            <path
                d="M44 0H0V44"
                stroke="currentColor"
                stroke-opacity=".08"
            />
        </pattern>
    </defs>

    <rect
        width="920"
        height="640"
        fill="url(#auth-grid)"
    />

    <g
        stroke="url(#auth-route-gradient)"
        stroke-width="1.8"
    >
        <path d="M82 448C202 338 290 330 404 373C532 421 615 282 838 184"/>
        <path d="M99 150C222 220 260 313 390 304C535 294 626 155 810 104"/>
        <path d="M137 520C270 440 388 484 505 421C637 350 713 390 850 502"/>
        <path d="M210 82C294 140 327 218 442 206C552 194 668 84 812 160"/>
        <path d="M185 286C294 255 347 135 480 126C616 116 699 239 806 274"/>
    </g>

    <g
        stroke="#21c7e8"
        stroke-opacity=".28"
        stroke-dasharray="4 9"
    >
        <circle cx="458" cy="322" r="192"/>
        <circle cx="458" cy="322" r="276"/>
        <path d="M458 28V616"/>
        <path d="M48 322H872"/>
    </g>

    <g fill="url(#auth-node-glow)" filter="url(#auth-glow)">
        <circle cx="99" cy="150" r="25"/>
        <circle cx="210" cy="82" r="23"/>
        <circle cx="390" cy="304" r="27"/>
        <circle cx="505" cy="421" r="28"/>
        <circle cx="615" cy="282" r="23"/>
        <circle cx="810" cy="104" r="25"/>
        <circle cx="838" cy="184" r="26"/>
        <circle cx="850" cy="502" r="24"/>
    </g>

    <g>
        <g transform="translate(99 150)">
            <circle r="9" fill="#21c7e8"/>
            <circle r="16" stroke="#21c7e8" stroke-opacity=".28"/>
        </g>

        <g transform="translate(210 82)">
            <circle r="8" fill="#35d39a"/>
            <circle r="15" stroke="#35d39a" stroke-opacity=".28"/>
        </g>

        <g transform="translate(390 304)">
            <circle r="9" fill="#21c7e8"/>
            <circle r="19" stroke="#21c7e8" stroke-opacity=".3"/>
        </g>

        <g transform="translate(505 421)">
            <circle r="9" fill="#f5ad45"/>
            <circle r="19" stroke="#f5ad45" stroke-opacity=".3"/>
        </g>

        <g transform="translate(615 282)">
            <circle r="8" fill="#21c7e8"/>
            <circle r="15" stroke="#21c7e8" stroke-opacity=".28"/>
        </g>

        <g transform="translate(810 104)">
            <circle r="8" fill="#9c7cff"/>
            <circle r="15" stroke="#9c7cff" stroke-opacity=".28"/>
        </g>

        <g transform="translate(838 184)">
            <circle r="9" fill="#21c7e8"/>
            <circle r="17" stroke="#21c7e8" stroke-opacity=".28"/>
        </g>

        <g transform="translate(850 502)">
            <circle r="8" fill="#35d39a"/>
            <circle r="15" stroke="#35d39a" stroke-opacity=".28"/>
        </g>
    </g>

    <g class="auth-network-labels">
        <text x="72" y="126">AS64512</text>
        <text x="176" y="57">2001:db8::/32</text>
        <text x="336" y="275">RPKI VALID</text>
        <text x="459" y="454">IRR OBJECT</text>
        <text x="586" y="256">AS65001</text>
        <text x="756" y="78">IPv6</text>
        <text x="782" y="216">203.0.113.0/24</text>
        <text x="785" y="538">AUDIT</text>
    </g>
</svg>
