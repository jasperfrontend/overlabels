<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => in_array($appearance ?? 'system', ['dark', 'sepia']), 'theme-sepia' => ($appearance ?? 'system') === 'sepia'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            html.theme-sepia {
                background-color: hsl(30 7% 8%);
            }
        </style>
        <link rel="icon" href="/favicon.png" sizes="any">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

        {{-- Open Graph / Social sharing (server-rendered so scrapers can read them).
             Controllers can override per-route by sharing an `$og` array via
             `view()->share('og', [...])` (see e.g. OverlayTemplateController::servePublic). --}}
        @php
            $ogDefaults = [
                'type' => 'website',
                'url' => 'https://overlabels.com/',
                'site_name' => 'Overlabels',
                'title' => 'Overlabels - A live overlay DSL for Twitch streamers',
                'description' => 'Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Free, open source overlay engine for OBS.',
                'image' => 'https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg',
                'image_alt' => 'Overlabels - write HTML and CSS, bind live Twitch data with triple-bracket tags',
                'twitter_card' => 'summary_large_image',
            ];
            $ogData = array_merge($ogDefaults, $og ?? []);
        @endphp
        <meta name="description" content="{{ $ogData['description'] }}" />
        <meta property="og:type" content="{{ $ogData['type'] }}" />
        <meta property="og:url" content="{{ $ogData['url'] }}" />
        <meta property="og:site_name" content="{{ $ogData['site_name'] }}" />
        <meta property="og:title" content="{{ $ogData['title'] }}" />
        <meta property="og:description" content="{{ $ogData['description'] }}" />
        <meta property="og:image" content="{{ $ogData['image'] }}" />
        <meta property="og:image:alt" content="{{ $ogData['image_alt'] }}" />
        <meta name="twitter:card" content="{{ $ogData['twitter_card'] }}" />
        <meta name="twitter:title" content="{{ $ogData['title'] }}" />
        <meta name="twitter:description" content="{{ $ogData['description'] }}" />
        <meta name="twitter:image" content="{{ $ogData['image'] }}" />
        <meta name="twitter:image:alt" content="{{ $ogData['image_alt'] }}" />

        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
