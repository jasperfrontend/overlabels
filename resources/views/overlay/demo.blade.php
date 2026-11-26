<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.svg" sizes="any">
    <title>{{ $name }} demo - Overlabels</title>
    {{-- A product's public demo (OverlayRenderer `demo` mode): its real
         overlay with sample values, framed by the homepage and the product
         page, which play the designer's scenes into it. --}}
    @vite('resources/js/overlay/demo.js')
    <style>
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: transparent; }
        #overlay-content,
        #overlay-content > .overlay-static-root { display: contents; }
    </style>
</head>
<body>
<div id="overlay-content"></div>
<script>
    window.__OVERLAY_DEMO__ = { product: @json($product) };
</script>
</body>
</html>
