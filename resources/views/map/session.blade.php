<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.png" sizes="any">
    @php
        $pageTitle = $streamerName."'s session - Overlabels";
        $resolvedOgImage = !empty($ogImagePath)
            ? (str_starts_with($ogImagePath, 'http') ? $ogImagePath : url($ogImagePath))
            : url('/ogimage.png');
        $resolvedDescription = $ogDescription
            ?? "{$streamerName}'s GPS route, shared via Overlabels.";
        $canonical = url('/map/'.$slug.'/'.$sessionId);
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}" />
    <link rel="canonical" href="{{ $canonical }}" />

    <meta property="og:type" content="article" />
    <meta property="og:url" content="{{ $canonical }}" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="{{ $pageTitle }}" />
    <meta property="og:description" content="{{ $resolvedDescription }}" />
    <meta property="og:image" content="{{ $resolvedOgImage }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:type" content="image/png" />
    <meta property="og:image:alt" content="{{ $streamerName }}'s GPS route on Overlabels" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $pageTitle }}" />
    <meta name="twitter:description" content="{{ $resolvedDescription }}" />
    <meta name="twitter:image" content="{{ $resolvedOgImage }}" />

    @vite('resources/js/map/app.ts')
</head>
<body style="margin:0;padding:0;overflow:hidden;">
    <div id="map-root"></div>
    <script>
        window.__MAP__ = {
            type: 'session',
            slug: @json($slug),
            sessionId: @json($sessionId),
            streamerName: @json($streamerName),
            speedUnit: @json($speedUnit),
        };
    </script>
</body>
</html>
