<svg width="1200" height="630" viewBox="0 0 1200 630" version="1.1" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
    <g id="Artboard1" transform="matrix(0.625,0,0,0.583333,0,0)">
        <rect x="0" y="0" width="1920" height="1080" style="fill:rgb(33,19,54);"/>
        <clipPath id="_clip1">
            <rect x="0" y="0" width="1920" height="1080"/>
        </clipPath>
        <g clip-path="url(#_clip1)">
            <g transform="matrix(1.6,0,0,1.714286,14.8992,-75.771429)">
                <text x="50px" y="117px" style="font-family:'Albert Sans';font-size:16px;fill:rgb(213,213,213);">{{ strtoupper($eyebrow) }}</text>
            </g>
            <g transform="matrix(1.6,0,0,1.714286,14.8224,765.942857)">
                <text x="50px" y="117px" style="font-family:'Albert Sans';font-size:16px;fill:rgb(142,142,142);">{{ $url }}</text>
            </g>
            <g transform="matrix(1.6,0,0,1.714286,9.2,3.277714)">
                <text x="50px" y="117px" style="font-family:'Albert Sans';font-size:50px;fill:rgb(0,166,244);">{{ $title }}</text>
            </g>
            @if (!empty($snippetCode))
                <g transform="matrix(1.6,0,0,1.714286,14.8992,145.820571)">
                    <text x="50px" y="117px" style="font-family:'Albert Sans';font-size:16px;fill:rgb(213,213,213);">{{ strtoupper($snippetLabel ?? 'CODE') }}</text>
                </g>
                <g transform="matrix(1.6,0,0,1.714286,-2.1888,-193.827048)">
                    <text x="60px" y="342px" style="font-family:'Fira Code';font-size:20px;fill:rgb(166,132,255);">{{ $snippetCode }}</text>
                </g>
            @endif
            <g transform="matrix(1.6,0,0,1.714286,-1.088,-21.561905)">
                @foreach ($bodyLines as $i => $line)
                    <text x="60px" y="{{ 342 + ($i * 24) }}px" style="font-family:'Albert Sans';font-size:20px;fill:white;">{{ $line }}</text>
                @endforeach
            </g>
            {{-- Overlabels icon (3 staggered "Ir" marks) --}}
            <g transform="matrix(0.364081,0,0,0.390086,1521.521529,699.979971)">
                <g transform="matrix(958.333333,0,0,958.333333,253,1140)">
                    <path d="M0.151,0.06L0.05,0.06L0.05,-0.73L0.151,-0.73L0.151,0.06ZM0.257,0.06L0.05,0.06L0.05,-0.03L0.257,-0.03L0.257,0.06ZM0.257,-0.64L0.05,-0.64L0.05,-0.73L0.257,-0.73L0.257,-0.64Z" style="fill:white;fill-rule:nonzero;"/>
                </g>
            </g>
            <g transform="matrix(0.364081,0,0,0.390086,1581.846357,760.501359)">
                <g transform="matrix(958.333333,0,0,958.333333,253,1140)">
                    <path d="M0.151,0.06L0.05,0.06L0.05,-0.73L0.151,-0.73L0.151,0.06ZM0.257,0.06L0.05,0.06L0.05,-0.03L0.257,-0.03L0.257,0.06ZM0.257,-0.64L0.05,-0.64L0.05,-0.73L0.257,-0.73L0.257,-0.64Z" style="fill:white;fill-rule:nonzero;"/>
                </g>
            </g>
            <g transform="matrix(0.364081,0,0,0.390086,1642.217534,821.090885)">
                <g transform="matrix(958.333333,0,0,958.333333,253,1140)">
                    <path d="M0.151,0.06L0.05,0.06L0.05,-0.73L0.151,-0.73L0.151,0.06ZM0.257,0.06L0.05,0.06L0.05,-0.03L0.257,-0.03L0.257,0.06ZM0.257,-0.64L0.05,-0.64L0.05,-0.73L0.257,-0.73L0.257,-0.64Z" style="fill:white;fill-rule:nonzero;"/>
                </g>
            </g>
        </g>
    </g>
</svg>
