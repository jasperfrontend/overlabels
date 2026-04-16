<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.png" sizes="any">
    <title>{{ $streamerName }}'s live location - Overlabels</title>
    @vite('resources/js/map/app.ts')
</head>
<body style="margin:0;padding:0;overflow:hidden;">
    <div id="map-root"></div>
    <script>
        window.__MAP__ = {
            type: 'live',
            twitchId: @json($twitchId),
            streamerName: @json($streamerName),
            delay: @json($delay),
            speedUnit: @json($speedUnit),
            isLive: @json($isLive),
        };
    </script>
</body>
</html>
