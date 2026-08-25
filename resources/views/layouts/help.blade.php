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
        html.dark { background-color: oklch(0.145 0 0); }
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
<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <header class="border-b border-sidebar-border">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="/help" class="flex items-center gap-2 cursor-pointer">
                <img src="/favicon.png" width="26" alt="" class="size-6" />
                <span class="font-semibold">Overlabels Help</span>
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="/help" class="cursor-pointer hover:underline {{ ($helpSection ?? '') === 'docs' ? 'text-foreground font-medium' : 'text-muted-foreground' }}">Guides</a>
                <a href="/help/reference" class="cursor-pointer hover:underline {{ ($helpSection ?? '') === 'reference' ? 'text-foreground font-medium' : 'text-muted-foreground' }}">Reference</a>
                <a href="/updates" class="cursor-pointer text-muted-foreground hover:underline">Updates</a>
                <a href="/dashboard" class="cursor-pointer text-muted-foreground hover:underline">Dashboard</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        {{--
            One search box for the whole corpus - tutorials, guides and reference
            entries alike. Before this, search existed only on the reference and
            covered only the reference, so the answer to "where do I search the
            docs" depended on which of two unrelated pages you happened to be on.
        --}}
        <div class="relative mb-4">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24" fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground"
                aria-hidden="true"
                width="16"
                height="16"
            >
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <label for="help-search" class="sr-only">Search the documentation</label>
            <input
                id="help-search"
                type="text"
                placeholder="Search everything (e.g. chat, follower, raid, hype train)..."
                class="w-full py-2 pl-9 pr-9 text-sm input-border"
                autocomplete="off"
            />
            <button
                id="help-search-clear"
                type="button"
                aria-label="Clear search"
                class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer rounded p-1 text-muted-foreground hover:bg-accent hidden"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="grid gap-6 md:grid-cols-[280px_minmax(0,1fr)]">
            <aside
                id="help-sidebar"
                class="max-h-[calc(100vh-12rem)] overflow-y-auto border border-sidebar-border p-2"
            >
                {{--
                    The sidebar is section-aware rather than one list of everything:
                    a 147-entry tag tree is the right nav for the reference and the
                    wrong nav for a tutorial. Search spans everything; browsing
                    stays scoped to where you are.
                --}}
                <div id="help-nav-tree">
                    @foreach ($navGroups ?? [] as $group)
                        <div class="px-2 pt-2 pb-1 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">
                            {{ $group['label'] }}
                            @if (!empty($group['count']))
                                <span class="ml-1 normal-case font-normal text-muted-foreground/50">({{ $group['count'] }})</span>
                            @endif
                        </div>
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ $item['url'] }}"
                                @if (!empty($item['active'])) data-help-active aria-current="page" @endif
                                @class([
                                    'block rounded-md px-2 py-1 text-xs cursor-pointer hover:bg-sidebar-accent',
                                    'font-mono' => !empty($group['mono']),
                                    'bg-card text-violet-400' => !empty($item['active']),
                                    'text-foreground' => empty($item['active']),
                                ])
                            >{{ $item['title'] }}</a>
                        @endforeach
                    @endforeach
                </div>
                <div id="help-search-results" class="hidden"></div>
            </aside>

            <article class="min-w-0">
                @yield('content')
            </article>
        </div>
    </main>

    <footer class="border-t border-sidebar-border">
        <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-foreground">
            Building an overlay with an AI assistant? The complete authoring guide is one plain text file:
            <a href="/llms.txt" class="font-mono underline cursor-pointer">https://overlabels.com/llms.txt</a>
            (<a href="/help/llms-txt" class="underline cursor-pointer">what is this?</a>).
        </div>
    </footer>

    <div id="help-toast-root" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
