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
    {{-- Duplicated from app.blade.php on purpose: the homepage is a standalone
         view (routes/web.php `view('welcome')`), not the Inertia shell, so it
         inherits nothing from it. This is the page a crawler reaches first. --}}
    <link rel="llms-txt" type="text/plain" href="/llms.txt" title="Overlabels authoring guide for LLMs">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

    <title>Overlabels &bull; Free Twitch overlays and games your Twitch chat plays live</title>
    <meta name="description" content="Chat Tower, Follower Bowling, Chat Checkin and a Twitch chat overlay: free games and overlays your Twitch chat plays live on your stream, installed in one click, one browser source in OBS. Tips from Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne land on your Twitch stream too. Open source." />
    <link rel="canonical" href="https://overlabels.com/" />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Overlabels • Free Twitch overlays and games your Twitch chat plays live" />
    <meta property="og:description" content="Chat Tower, Follower Bowling, Chat Checkin and a Twitch chat overlay: free games and overlays your Twitch chat plays live on your stream, installed in one click, one browser source in OBS. Tips from Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne land on your Twitch stream too. Open source." />
    <meta property="og:image" content="{{ asset('ogimage.jpg') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels • games your Twitch chat plays live on your stream" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Overlabels • Free Twitch overlays and games your Twitch chat plays live" />
    <meta name="twitter:description" content="Chat Tower, Follower Bowling, Chat Checkin and a Twitch chat overlay: free games and overlays your Twitch chat plays live on your stream, installed in one click, one browser source in OBS. Tips from Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne land on your Twitch stream too. Open source." />
    <meta name="twitter:image" content="{{ asset('ogimage.jpg') }}" />
    <meta name="twitter:image:alt" content="Overlabels • games your Twitch chat plays live on your stream" />

    @vite(['resources/js/welcome/app.ts'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-sidebar-accent text-foreground">
        @include('welcome.navbar')
        {{-- For streamers: the products, how they install, the alerts, why here. --}}
        @include('welcome.hero')
        @include('welcome.products')
        @include('welcome.how')
        @include('welcome.alerts')
        @include('welcome.why')
        {{-- For builders: the original homepage, kept whole under one fold.
             Its sections and anchors (#tags, #controls, #conditionals,
             #events, #integrations, #kits) are what the page ranks on. --}}
        @include('welcome.build')
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
