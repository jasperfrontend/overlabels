<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => in_array($appearance ?? 'system', ['dark', 'sepia']), 'theme-sepia' => ($appearance ?? 'system') === 'sepia'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Resolve theme before first paint: localStorage wins, cookie-derived server value is the fallback. --}}
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
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

    <title>Overlabels &bull; Reactive Twitch overlays for people who code</title>
    <meta name="description" content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source." />
    <link rel="canonical" href="https://overlabels.com/" />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Overlabels • Reactive Twitch overlays for people who code" />
    <meta property="og:description" content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts and donation tracking from Ko-fi, Streamlabs, StreamElements, Buy Me A Coffee, FourthWall and Throne. Free and open source." />
    <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels • reactive Twitch overlays for people who code" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Overlabels • Reactive Twitch overlays for people who code" />
    <meta name="twitter:description" content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source." />
    <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels • reactive Twitch overlays for people who code" />

    @vite(['resources/js/welcome/app.ts'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-sidebar-accent text-foreground">
        @include('welcome.navbar')
        @include('welcome.hero')
        @include('welcome.syntax')
        @include('welcome.controls')
        @include('welcome.conditionals')
        @include('welcome.events')
        @include('welcome.integrations')
        @include('welcome.kits')
        @include('welcome.onboarding')
        @include('welcome.cta')
        @include('welcome.footer')
    </div>
</body>
</html>
