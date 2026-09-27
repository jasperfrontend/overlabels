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
<body class="font-sans antialiased">
    <div class="min-h-screen bg-sidebar-accent text-foreground">
        @include('welcome.navbar')

        @php
            // Hand-kept, same as welcome/products.blade.php's $demos map: a
            // product with a partial here gets it running, one without falls
            // back to its hero image.
            $demos = [
                'chat-tower' => 'tower',
                'follower-bowling' => 'bowling',
                'chat-checkin' => 'checkin',
                'twitch-chat-overlay' => 'chat',
                'chat-emote-bubbles' => 'bubbles',
            ];
            $demo = $demos[$product['slug']] ?? null;
            $command = preg_match('/!\w+/', $product['description'], $m) ? $m[0] : null;
        @endphp

        <section class="border-b border-b-sidebar-border bg-card py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <div class="min-w-0">
                        @if ($demo)
                            @include('welcome.demos.'.$demo)
                        @elseif (!empty($product['hero']))
                            <img src="{{ $product['hero'] }}" alt="" class="block aspect-video w-full rounded-sm border border-sidebar-border object-cover" width="1280" height="720" loading="lazy" />
                        @endif
                    </div>

                    <div class="mt-8 grid gap-8 sm:mt-10 md:grid-cols-[1.1fr_1fr] md:gap-12 lg:gap-16">
                        <div class="min-w-0">
                            @if ($command)
                                <span class="mb-4 inline-block border border-violet-400/60 px-2 py-0.5 font-mono text-xs text-violet-600 dark:text-violet-300">Twitch chat types {{ $command }}</span>
                            @else
                                <span class="mb-4 inline-block border border-sidebar-border px-2 py-0.5 text-xs text-muted-foreground">no bot needed</span>
                            @endif
                            <h1 class="mb-4 text-3xl leading-[1.1] font-bold tracking-tight sm:text-4xl">{{ $product['name'] }}</h1>
                            <p class="max-w-lg text-lg leading-relaxed text-foreground">{{ $product['description'] }}</p>
                        </div>

                        <div class="min-w-0 md:pt-1">
                            @if ($product['highlights'])
                                <ul class="mb-8 flex flex-col gap-3">
                                    @foreach ($product['highlights'] as $line)
                                        <li class="flex items-start gap-3 text-base text-foreground">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mt-1 h-4 w-4 shrink-0 text-sky-500"><path d="M20 6 9 17l-5-5"/></svg>
                                            <span>{{ $line }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                                <a href="{{ route('products.manage', $product['slug']) }}" class="btn btn-primary w-full cursor-pointer sm:w-auto">
                                    {{ $installed ? 'Manage '.$product['name'] : 'Get '.$product['name'] }}
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                </a>
                                @unless ($installed)
                                    <span class="text-xs text-muted-foreground">Free. One click. One link into OBS.</span>
                                @endunless
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @include('welcome.footer')
    </div>
</body>
</html>
