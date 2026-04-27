<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';
            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        html { background-color: oklch(1 0 0); }
        html.dark { background-color: oklch(0.145 0 0); }
    </style>

    <link rel="icon" href="/favicon.png" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

    <title>{{ $pageTitle ?? 'Reference - Overlabels' }}</title>
    <meta name="description" content="{{ $pageDescription ?? '' }}" />
    @if (!empty($canonicalUrl))
        <link rel="canonical" href="{{ $canonicalUrl }}" />
    @endif

    @php
        $resolvedOgImage = !empty($ogImage)
            ? (str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage))
            : 'https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg';
    @endphp

    <meta property="og:type" content="website" />
    @if (!empty($canonicalUrl))
        <meta property="og:url" content="{{ $canonicalUrl }}" />
    @endif
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="{{ $pageTitle ?? 'Reference - Overlabels' }}" />
    <meta property="og:description" content="{{ $pageDescription ?? '' }}" />
    <meta property="og:image" content="{{ $resolvedOgImage }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:type" content="image/png" />
    <meta property="og:image:alt" content="{{ $pageTitle ?? 'Overlabels - build Twitch overlays with HTML, CSS, and live data' }}" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $pageTitle ?? 'Reference - Overlabels' }}" />
    <meta name="twitter:description" content="{{ $pageDescription ?? '' }}" />
    <meta name="twitter:image" content="{{ $resolvedOgImage }}" />

    @vite(['resources/js/help-reference/main.ts'])
</head>
<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <header class="border-b border-sidebar-border">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="/" class="flex items-center gap-2 cursor-pointer">
                <img src="/favicon.png" alt="" class="size-6" />
                <span class="font-semibold">Overlabels</span>
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="/help" class="text-foreground hover:underline cursor-pointer">Help</a>
                <a href="/" class="text-muted-foreground hover:underline cursor-pointer">Back to app</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @yield('content')
    </main>

    <div id="help-toast-root" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
