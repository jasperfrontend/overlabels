{{--
    OG card for a single /updates post.

    Same palette, fonts and logo as og/help-reference, but authored in plain
    1200x630 coordinates rather than that card's exported 1920x1080 artboard
    matrices. The two nested transforms there cancel out exactly (a 16px glyph
    renders at 16px), so these coordinates sit at the same visual rhythm while
    staying readable to edit.

    The logo keeps its original artboard transform verbatim so it is pixel
    identical to the reference card - do not re-derive it.

    Layout is vertical-flow: the title takes 1-3 lines and $bodyTop is computed
    from how many, so a long title pushes the excerpt down instead of sitting
    on top of it. See OgImageService::contextForUpdate().
--}}
<svg width="1200" height="630" viewBox="0 0 1200 630" version="1.1" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;">
    <rect x="0" y="0" width="1200" height="630" style="fill:rgb(33,19,54);"/>

    {{-- Tag + publish date, e.g. "BOT · 26 AUG 2026" --}}
    <text x="59" y="73" style="font-family:'Albert Sans';font-size:16px;fill:rgb(213,213,213);">{{ $eyebrow }}</text>

    @foreach ($titleLines as $i => $line)
        <text x="56" y="{{ 140 + ($i * 58) }}" style="font-family:'Albert Sans';font-size:50px;fill:rgb(0,166,244);">{{ $line }}</text>
    @endforeach

    @foreach ($bodyLines as $i => $line)
        <text x="59" y="{{ $bodyTop + ($i * 24) }}" style="font-family:'Albert Sans';font-size:20px;fill:white;">{{ $line }}</text>
    @endforeach

    <text x="59" y="564" style="font-family:'Albert Sans';font-size:16px;fill:rgb(142,142,142);">{{ $url }}</text>

    {{-- Overlabels icon (3 staggered "Ir" marks), lifted verbatim from og/help-reference --}}
    <g transform="matrix(0.625,0,0,0.583333,0,0)">
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
</svg>
