<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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
        </style>

        <link rel="icon" href="/favicon.png" sizes="any">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

        {{-- Open Graph / Social sharing (server-rendered so scrapers can read them) --}}
        <meta property="og:type" content="website" />
        <meta property="og:url" content="https://overlabels.com/" />
        <meta property="og:site_name" content="Overlabels" />
        <meta property="og:title" content="Overlabels — A live overlay DSL for Twitch streamers" />
        <meta property="og:description" content="Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Free, open source overlay engine for OBS." />
        <meta property="og:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:image:alt" content="Overlabels — write HTML and CSS, bind live Twitch data with triple-bracket tags" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="Overlabels — A live overlay DSL for Twitch streamers" />
        <meta name="twitter:description" content="Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Free, open source overlay engine for OBS." />
        <meta name="twitter:image" content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
        <meta name="twitter:image:alt" content="Overlabels — write HTML and CSS, bind live Twitch data with triple-bracket tags" />

        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead

        {{-- Cloudinary Upload Widget --}}
        <script src="https://widget.cloudinary.com/v2.0/global/all.js" type="text/javascript"></script>
        <script>
            window.cloudinaryCloudName = '{{ config("services.cloudinary.cloud_name") }}';
        </script>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
