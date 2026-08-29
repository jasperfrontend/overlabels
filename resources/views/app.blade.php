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
        {{-- Discovery hint for the machine-readable authoring guide. The root
             path is the real contract (llmstxt.org fixes /llms.txt and defines
             no link relation), so this is a hint on top, not the contract.
             `rel="llms-txt"` rather than `rel="alternate"` on purpose: llms.txt
             is not an alternate representation of whatever page you are on, and
             claiming so would be a lie to every crawler that understands it. --}}
        <link rel="llms-txt" type="text/plain" href="/llms.txt" title="Overlabels authoring guide for LLMs">
        {{-- Markdown twin of this specific page, where one exists. Shared by
             the controller (see OverlayTemplateController::servePublic).
             `rel="alternate"` is accurate here in a way it would not be for
             llms.txt above: the .md is genuinely this page's content in
             another representation, not a separate document. --}}
        @isset($alternateMarkdown)
            <link rel="alternate" type="text/markdown" href="{{ $alternateMarkdown }}" title="This overlay as markdown">
        @endisset
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
                'image' => asset('ogimage.jpg'),
                'image_alt' => 'Overlabels - write HTML and CSS, bind live Twitch data with triple-bracket tags',
                // The default card is the 1200x630 JPEG; a controller shipping
                // a generated PNG overrides the type along with the image.
                'image_width' => 1200,
                'image_height' => 630,
                'image_type' => 'image/jpeg',
                'twitter_card' => 'summary_large_image',
                'published_time' => null,
                'modified_time' => null,
                'tags' => [],
            ];
            $ogData = array_merge($ogDefaults, $og ?? []);
        @endphp
        {{-- Server-rendered so a crawler that does not run JavaScript still
             gets one. Inertia's <Head> sets document.title on mount, which
             replaces this element's text rather than adding a second one. --}}
        <title>{{ $pageTitle ?? $ogData['title'] }}</title>
        <meta name="description" content="{{ $ogData['description'] }}" />
        @isset($canonical)
            <link rel="canonical" href="{{ $canonical }}" />
        @endisset
        @isset($robots)
            <meta name="robots" content="{{ $robots }}" />
        @endisset
        <meta property="og:type" content="{{ $ogData['type'] }}" />
        <meta property="og:url" content="{{ $ogData['url'] }}" />
        <meta property="og:site_name" content="{{ $ogData['site_name'] }}" />
        <meta property="og:title" content="{{ $ogData['title'] }}" />
        <meta property="og:description" content="{{ $ogData['description'] }}" />
        <meta property="og:image" content="{{ $ogData['image'] }}" />
        <meta property="og:image:alt" content="{{ $ogData['image_alt'] }}" />
        <meta property="og:image:width" content="{{ $ogData['image_width'] }}" />
        <meta property="og:image:height" content="{{ $ogData['image_height'] }}" />
        <meta property="og:image:type" content="{{ $ogData['image_type'] }}" />
        @if ($ogData['type'] === 'article')
            @if (!empty($ogData['published_time']))
                <meta property="article:published_time" content="{{ $ogData['published_time'] }}" />
            @endif
            @if (!empty($ogData['modified_time']))
                <meta property="article:modified_time" content="{{ $ogData['modified_time'] }}" />
            @endif
            @foreach ($ogData['tags'] as $ogTag)
                <meta property="article:tag" content="{{ $ogTag }}" />
            @endforeach
        @endif
        <meta name="twitter:card" content="{{ $ogData['twitter_card'] }}" />
        <meta name="twitter:title" content="{{ $ogData['title'] }}" />
        <meta name="twitter:description" content="{{ $ogData['description'] }}" />
        <meta name="twitter:image" content="{{ $ogData['image'] }}" />
        <meta name="twitter:image:alt" content="{{ $ogData['image_alt'] }}" />

        {{-- Structured data, where a controller supplied any. JSON_HEX_TAG is
             what stops an author-written title containing "</script>" from
             closing this block early; the escaped form is still valid JSON. --}}
        @isset($jsonLd)
            <script type="application/ld+json">{!! json_encode(
                array_filter($jsonLd, fn ($value) => $value !== null),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
            ) !!}</script>
        @endisset

        @php
            // During impersonation auth()->user() is the (non-admin) target, so
            // isAdmin() is false and the 'user' group would drop every admin.*
            // route - including admin.impersonate.stop that the Stop button calls.
            // The real operator is a verified admin (HandleImpersonation checks
            // real_admin_id before swapping), so ship the admin group instead.
            $isImpersonating = session()->has('impersonating_user_id') && session()->has('real_admin_id');
            $ziggyGroup = ! auth()->check()
                ? 'guest'
                : ((auth()->user()->isAdmin() || $isImpersonating) ? 'admin' : 'user');
        @endphp
        @routes($ziggyGroup)
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
