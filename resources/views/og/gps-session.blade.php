<svg width="1200" height="630" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg">
    {{-- Background gradient: dark purple, matches the route page palette --}}
    <defs>
        <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#1a0f3a"/>
            <stop offset="100%" stop-color="#0a0419"/>
        </linearGradient>
        <linearGradient id="route" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#a78bfa"/>
            <stop offset="100%" stop-color="#7c3aed"/>
        </linearGradient>
    </defs>

    <rect width="1200" height="630" fill="url(#bg)"/>

    {{-- Eyebrow + streamer name + date (top of right column) --}}
    <text x="720" y="100" font-family="Albert Sans" font-size="18" fill="#a78bfa" letter-spacing="2">OVERLABELS - GPS SESSION</text>
    <text x="720" y="160" font-family="Albert Sans" font-size="56" font-weight="600" fill="#ffffff">{{ $streamerName }}</text>
    @if (!empty($dateLabel))
        <text x="720" y="200" font-family="Albert Sans" font-size="22" fill="#c4b5fd">{{ $dateLabel }}</text>
    @endif

    {{-- Route panel: subtle card behind the polyline --}}
    <rect x="{{ $panelX }}" y="{{ $panelY }}" width="{{ $panelWidth }}" height="{{ $panelHeight }}" rx="16" fill="#150a2e" stroke="#2a1a52" stroke-width="2"/>

    {{-- Faint grid pattern inside the panel for that "map without tiles" feel --}}
    @php
        $gridStep = 40;
        $gridStartX = $panelX + ($gridStep - (int) $panelX % $gridStep) % $gridStep;
        $gridStartY = $panelY + ($gridStep - (int) $panelY % $gridStep) % $gridStep;
    @endphp
    @for ($gx = $gridStartX; $gx < $panelX + $panelWidth; $gx += $gridStep)
        <line x1="{{ $gx }}" y1="{{ $panelY + 4 }}" x2="{{ $gx }}" y2="{{ $panelY + $panelHeight - 4 }}" stroke="#241548" stroke-width="1"/>
    @endfor
    @for ($gy = $gridStartY; $gy < $panelY + $panelHeight; $gy += $gridStep)
        <line x1="{{ $panelX + 4 }}" y1="{{ $gy }}" x2="{{ $panelX + $panelWidth - 4 }}" y2="{{ $gy }}" stroke="#241548" stroke-width="1"/>
    @endfor

    {{-- The actual route. Stroked twice: a fat blurred shadow underneath, then the crisp line on top, for a subtle glow. --}}
    @if (!empty($routePoints))
        <polyline points="{{ $routePoints }}" fill="none" stroke="#7c3aed" stroke-width="14" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"/>
        <polyline points="{{ $routePoints }}" fill="none" stroke="url(#route)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
    @else
        <text x="{{ $panelX + $panelWidth / 2 }}" y="{{ $panelY + $panelHeight / 2 }}" text-anchor="middle" font-family="Albert Sans" font-size="20" fill="#6b5f8f">No route data</text>
    @endif

    {{-- Start (green) and end (red) markers --}}
    @if (!empty($startMarker))
        <circle cx="{{ $startMarker['x'] }}" cy="{{ $startMarker['y'] }}" r="11" fill="#22c55e" stroke="#0a0419" stroke-width="3"/>
    @endif
    @if (!empty($endMarker))
        <circle cx="{{ $endMarker['x'] }}" cy="{{ $endMarker['y'] }}" r="11" fill="#ef4444" stroke="#0a0419" stroke-width="3"/>
    @endif

    {{-- Stat tiles (right column, 2x2 grid) --}}
    @php
        $tiles = [
            ['label' => 'DISTANCE',  'value' => $distanceLabel,  'x' => 720, 'y' => 240],
            ['label' => 'DURATION',  'value' => $durationLabel,  'x' => 950, 'y' => 240],
            ['label' => 'MAX SPEED', 'value' => $maxSpeedLabel,  'x' => 720, 'y' => 360],
            ['label' => 'PINGS',     'value' => $pingLabel,      'x' => 950, 'y' => 360],
        ];
    @endphp
    @foreach ($tiles as $tile)
        <rect x="{{ $tile['x'] }}" y="{{ $tile['y'] }}" width="200" height="100" rx="12" fill="#150a2e" stroke="#2a1a52" stroke-width="1.5"/>
        <text x="{{ $tile['x'] + 16 }}" y="{{ $tile['y'] + 30 }}" font-family="Albert Sans" font-size="13" fill="#a78bfa" letter-spacing="1.5">{{ $tile['label'] }}</text>
        <text x="{{ $tile['x'] + 16 }}" y="{{ $tile['y'] + 75 }}" font-family="Albert Sans" font-size="32" font-weight="600" fill="#ffffff">{{ $tile['value'] }}</text>
    @endforeach

    {{-- Footer: URL --}}
    <text x="60" y="610" font-family="Albert Sans" font-size="18" fill="#8e8e9f">{{ $url }}</text>

    {{-- Overlabels brand mark: 3 staggered "Ir" glyphs. Scaled down vs. the
         help-reference OG (0.18 outer scale) so the full Z-stagger fits cleanly
         in the bottom-right of the 1200x630 canvas with no clipping. --}}
    @php
        $brandX = 1075;
        $brandY = 480;
        $brandStaggerX = 22;
        $brandStaggerY = 22;
        $brandScaleX = 0.18;
        $brandScaleY = 0.193;
    @endphp
    @for ($i = 0; $i < 3; $i++)
        <g transform="matrix({{ $brandScaleX }},0,0,{{ $brandScaleY }},{{ $brandX + $i * $brandStaggerX }},{{ $brandY + $i * $brandStaggerY }})">
            <g transform="matrix(958.333333,0,0,958.333333,253,1140)">
                <path d="M0.151,0.06L0.05,0.06L0.05,-0.73L0.151,-0.73L0.151,0.06ZM0.257,0.06L0.05,0.06L0.05,-0.03L0.257,-0.03L0.257,0.06ZM0.257,-0.64L0.05,-0.64L0.05,-0.73L0.257,-0.73L0.257,-0.64Z" fill="#ffffff" fill-rule="nonzero"/>
            </g>
        </g>
    @endfor
</svg>
