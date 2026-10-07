{{-- The <head> both product templates share: page.blade.php for a product with a pitch, pitch.blade.php for one without. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => in_array($appearance ?? 'system', ['dark', 'sepia']), 'theme-sepia' => ($appearance ?? 'system') === 'sepia'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Resolve theme before first paint: same script welcome.blade.php uses. --}}
    <script>
        (function () {
            let appearance = '{{ $appearance ?? 'system' }}';
            try { appearance = localStorage.getItem('appearance') || appearance; } catch (e) {}
            const root = document.documentElement;
            root.classList.remove('dark', 'theme-sepia');
            if (appearance === 'sepia') {
                root.classList.add('dark', 'theme-sepia');
            } else if (appearance === 'dark') {
                root.classList.add('dark');
            } else if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                root.classList.add('dark');
            }
        })();
    </script>
    <style>
        html { background-color: oklch(1 0 0); }
        html.dark { background-color: oklch(0.145 0 0); }
        html.theme-sepia { background-color: hsl(30 7% 8%); }
    </style>

    <link rel="icon" href="/favicon.png" sizes="any">
    {{-- Duplicated from welcome.blade.php on purpose: this is a standalone
         view (routes/web.php -> ProductController::pitch), not the Inertia
         shell, so it inherits nothing from app.blade.php. --}}
    <link rel="llms-txt" type="text/plain" href="/llms.txt" title="Overlabels authoring guide for LLMs">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

    <title>{{ $product['name'] }} - Overlabels product</title>
    <meta name="description" content="{{ $product['description'] }}" />
    <link rel="canonical" href="{{ $canonical }}" />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ $canonical }}" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="{{ $product['name'] }} - Overlabels product" />
    <meta property="og:description" content="{{ $product['description'] }}" />
    <meta property="og:image" content="{{ url($product['hero'] ?? '/ogimage.jpg') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $product['name'] }} - Overlabels product" />
    <meta name="twitter:description" content="{{ $product['description'] }}" />
    <meta name="twitter:image" content="{{ url($product['hero'] ?? '/ogimage.jpg') }}" />

    {{-- JSON_HEX_TAG etc. are what stop an author-written title containing
         "</script>" from closing this block early; see app.blade.php. --}}
    @isset($jsonLd)
        <script type="application/ld+json">{!! json_encode(
            array_filter($jsonLd, fn ($value) => $value !== null),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) !!}</script>
    @endisset

    @vite(['resources/js/welcome/app.ts'])
</head>
