<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => in_array($appearance ?? 'system', ['dark', 'sepia']), 'theme-sepia' => ($appearance ?? 'system') === 'sepia'])>
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
        html.dark { background-color: #1d0b30; }
        html.theme-sepia { background-color: hsl(30 7% 8%); }
    </style>

    <link rel="icon" href="/favicon.png" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

    {{--
        Every page under /help is server-rendered HTML, so this is the whole
        documentation site as far as a crawler is concerned. It used to be the
        reference only, with the prose pages served as empty Inertia shells.
        Declare llms.txt here too - app.blade.php and welcome.blade.php do.
    --}}
    <link rel="llms-txt" type="text/plain" href="/llms.txt" title="Overlabels authoring guide for LLMs">

    <title>{{ $pageTitle ?? 'Help - Overlabels' }}</title>
    <meta name="description" content="{{ $pageDescription ?? '' }}" />
    @if (!empty($canonicalUrl))
        <link rel="canonical" href="{{ $canonicalUrl }}" />
    @endif

    @php
        $resolvedOgImage = !empty($ogImage)
            ? (str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage))
            : asset('ogimage.jpg');
        $isLanding = ($helpSection ?? '') === 'landing';
    @endphp

    <meta property="og:type" content="website" />
    @if (!empty($canonicalUrl))
        <meta property="og:url" content="{{ $canonicalUrl }}" />
    @endif
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="{{ $pageTitle ?? 'Help - Overlabels' }}" />
    <meta property="og:description" content="{{ $pageDescription ?? '' }}" />
    <meta property="og:image" content="{{ $resolvedOgImage }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:type" content="image/png" />
    <meta property="og:image:alt" content="{{ $pageTitle ?? 'Overlabels - build Twitch overlays with HTML, CSS, and live data' }}" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $pageTitle ?? 'Help - Overlabels' }}" />
    <meta name="twitter:description" content="{{ $pageDescription ?? '' }}" />
    <meta name="twitter:image" content="{{ $resolvedOgImage }}" />

    @vite(['resources/js/help/main.ts'])
</head>
<body class="help-body font-sans antialiased text-foreground">
    {{--
        The signature page gradient sits behind everything, with a veil over
        it in dark mode so prose stays readable. In light and sepia the spots
        are transparent and the veil is off, so the page is simply its base
        colour - the same tokens the rest of the app uses.
    --}}
    <div class="help-backdrop" aria-hidden="true"></div>

    <div class="relative z-[1] flex min-h-screen flex-col">
        <header @class(['border-b border-sidebar-border' => !$isLanding])>
            <nav class="mx-auto flex h-[68px] max-w-[1240px] items-center justify-between gap-4 px-4 sm:px-6" aria-label="Help">
                <a href="/help" class="flex shrink-0 cursor-pointer items-center gap-2.5 text-foreground">
                    <img src="/favicon-light.svg" alt="" class="size-[22px] dark:hidden" /><img src="/favicon.png" alt="" class="hidden size-[22px] dark:block" />
                    <span class="text-base font-semibold tracking-tight">Overlabels</span>
                    <span class="help-eyebrow">Help</span>
                </a>
                <div class="flex min-w-0 items-center gap-3 sm:gap-5">
                    @unless ($isLanding)
                        @include('help._search', ['compact' => true])
                    @endunless
                    <a href="/help/reference" @class(['hidden cursor-pointer text-sm md:inline', 'text-foreground' => ($helpSection ?? '') === 'reference', 'text-muted-foreground hover:text-foreground' => ($helpSection ?? '') !== 'reference'])>Reference</a>
                    <a href="/updates" class="hidden cursor-pointer text-sm text-muted-foreground hover:text-foreground md:inline">Updates</a>
                    <a href="/#kits" class="hidden cursor-pointer text-sm text-muted-foreground hover:text-foreground lg:inline">Kits</a>
                    <a href="/dashboard" class="help-btn shrink-0">Open the dashboard</a>
                </div>
            </nav>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-sidebar-border">
            <div class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-x-8 gap-y-3 px-4 py-6 sm:px-6">
                <span class="text-[13px] text-muted-foreground">
                    Building an overlay with an AI assistant? The complete authoring guide is one plain text file:
                    <a href="/llms.txt" class="cursor-pointer font-mono text-foreground underline">llms.txt</a>
                    (<a href="/help/llms-txt" class="cursor-pointer underline">what is this?</a>).
                </span>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 font-mono text-xs">
                    <span class="text-muted-foreground/70">Machine-readable too:</span>
                    <a href="/help-reference-index.json" class="cursor-pointer text-muted-foreground hover:text-foreground">help-reference-index.json</a>
                    <a href="/help/markdown-endpoints" class="cursor-pointer text-muted-foreground hover:text-foreground">add .md to any URL</a>
                </div>
            </div>
        </footer>
    </div>

    <div id="help-toast-root" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
