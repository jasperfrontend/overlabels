{{--
  Who makes Overlabels. The page exists so that anyone approached by someone
  claiming to work for Overlabels can check it in one click (Terms 4.2 forbids
  the claim). Standalone Blade like the product pages: crawlable, and it
  inherits nothing from app.blade.php, so the head is written out here.
--}}
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
    <link rel="llms-txt" type="text/plain" href="/llms.txt" title="Overlabels authoring guide for LLMs">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:300,400,500,600,700" rel="stylesheet" />

    @php
        $metaTitle = 'About Overlabels - who makes it';
        $metaDescription = 'Overlabels is made by one person, Jasper (JasperDiscovers on Twitch). Nobody else works for Overlabels. If someone says they do, they do not.';
        $avatar = 'https://static-cdn.jtvnw.net/jtv_user_pictures/1d0df896-13c2-4f15-b6ca-f7d40d2fffc5-profile_image-300x300.png';
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}" />
    <link rel="canonical" href="{{ url('/about') }}" />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url('/about') }}" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="{{ $metaTitle }}" />
    <meta property="og:description" content="{{ $metaDescription }}" />
    <meta property="og:image" content="{{ url('/ogimage.jpg') }}" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $metaTitle }}" />
    <meta name="twitter:description" content="{{ $metaDescription }}" />
    <meta name="twitter:image" content="{{ url('/ogimage.jpg') }}" />

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'url' => url('/about'),
        'name' => $metaTitle,
        'about' => [
            '@type' => 'Person',
            'name' => 'Jasper',
            'alternateName' => 'JasperDiscovers',
            'image' => $avatar,
            'sameAs' => ['https://twitch.tv/jasperdiscovers', 'https://github.com/jasperfrontend'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    @vite(['resources/js/welcome/app.ts'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-sidebar-accent text-foreground">
        @include('welcome.navbar')

        @php
            $link = 'cursor-pointer text-sky-500 hover:underline';
            $code = 'font-mono text-[0.9em]';
        @endphp

        <article class="py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-lg leading-relaxed text-foreground [&_h2]:mt-14 [&_h2]:mb-4 [&_h2]:text-2xl [&_h2]:leading-tight [&_h2]:font-bold [&_p]:mb-5">
                    <h1 class="mb-8 text-3xl leading-[1.1] font-bold tracking-tight sm:text-4xl">Who makes Overlabels</h1>

                    <div class="mb-8 flex items-center gap-5">
                        <img src="{{ $avatar }}" alt="Jasper's Twitch profile picture" width="300" height="300"
                             class="size-20 shrink-0 rounded-full" />
                        <div class="min-w-0 text-base">
                            <span class="block text-xl font-bold">Jasper</span>
                            <a href="https://twitch.tv/jasperdiscovers" target="_blank" rel="noopener" class="{{ $link }}">JasperDiscovers on Twitch</a>
                            <span class="text-muted-foreground">·</span>
                            <a href="https://github.com/jasperfrontend" target="_blank" rel="noopener" class="{{ $link }}">JasperFrontend on GitHub</a>
                        </div>
                    </div>

                    <p>
                        Overlabels is my passion project. I've built it on my own since July 24th, 2025, when work on this
                        version started. I design it, write the code, answer support questions, and stream while I build it.
                    </p>

                    <h2>Why I started Overlabels</h2>
                    <p>
                        The question I wanted to answer was: can we write interactive overlays and alerts with just HTML, CSS
                        and "something else" that makes them interactive and safe to use?
                    </p>
                    <p>
                        That "something else" turned into a full-blown DSL: a domain-specific language, a little programming
                        language built for one specific job. It proved powerful enough to build an elaborate math engine on top
                        of, and that opened the gates for incredibly fine-grained animations.
                    </p>
                    <p>
                        But animations take a lot of code and know-how to pull off. So I started using the math engine, and
                        everything else I had built, to make products on top of my own engine myself. That is how
                        <a href="{{ route('products.show', 'chat-tower') }}" class="{{ $link }}">Chat Tower</a>,
                        <a href="{{ route('products.show', 'follower-bowling') }}" class="{{ $link }}">Follower Bowling</a>
                        and
                        <a href="{{ route('products.show', 'chat-plinko') }}" class="{{ $link }}">Chat Plinko</a>
                        were born. Next to them sit a full-featured
                        <a href="{{ route('products.show', 'twitch-chat-overlay') }}" class="{{ $link }}">Twitch Chat Overlay</a>,
                        <a href="{{ route('products.show', 'chat-checkin') }}" class="{{ $link }}">Chat Checkin</a>
                        and the super cute
                        <a href="{{ route('products.show', 'chat-emote-bubbles') }}" class="{{ $link }}">Chat Emote Bubbles</a>.
                    </p>
                    <p>
                        Overlabels is powerful enough to host your wildest ideas. If you can build it in HTML and CSS,
                        Overlabels gives it live data and a math engine that keeps pace with your screen, because every
                        animation runs on <code class="{{ $code }}">window.requestAnimationFrame</code>.
                    </p>
                    <p>
                        Sorry, I went super nerdy there. No regrets. One last thing I'm proud of: you can add up every donation
                        service you use into one single number with <code class="{{ $code }}">sum()</code>, or show the latest
                        donation from any of them with
                        <a href="/help/tutorials/latest-donator" class="{{ $link }}"><code class="{{ $code }}">latest()</code></a>.
                        I don't know of another overlay and alert provider that does that.
                    </p>
                    <p>
                        Thanks for reading this storm of words. Enjoy Overlabels, it's been a fun ride so far. Now read the
                        part below before you leave.
                    </p>

                    <h2>Nobody works for Overlabels</h2>
                    <p>
                        There is no team, no staff and no support department. There are no ambassadors, partners or recruiters
                        either. Overlabels is one person.
                    </p>

                    <h2>If someone says they're from Overlabels, they're not</h2>
                    <p>
                        Only one person will ever contact you about Overlabels, and that's me:
                        <a href="https://twitch.tv/jasperdiscovers" target="_blank" rel="noopener" class="{{ $link }}">JasperDiscovers</a>
                        on Twitch, and <code class="{{ $code }}">jasperdiscovers</code> (lowercase) on Discord.
                    </p>
                    <p>
                        Anyone else claiming to work for Overlabels, speak for it, or be connected to it is not telling the
                        truth. Our <a href="/terms" class="{{ $link }}">Terms of Service</a> forbid it. If it happens to you,
                        block them and let me know on Discord.
                    </p>
                    <p>
                        The @overlabels chat bot only answers commands in your chat. It cannot send whispers or DMs, so any
                        private message from an "Overlabels" account is not from us. Watch out for lookalike names such as
                        <code class="{{ $code }}">0verlabels</code> or <code class="{{ $code }}">overlabels_support</code>.
                    </p>

                    <h2>I will never ask you for</h2>
                    <ul class="mb-5 list-disc space-y-2 pl-6">
                        <li>your Twitch password or a login code</li>
                        <li>your overlay links (they contain a private access token)</li>
                        <li>money, gift subs, or payment to "unlock" or "fix" something</li>
                    </ul>

                    <p class="mt-14 text-muted-foreground">
                        If the team ever grows, everyone on it will be named on this page. If a name isn't here, that person
                        doesn't work on Overlabels.
                    </p>

                    <p class="mt-10 text-xl font-semibold">
                        Now go install <a href="{{ route('products.show', 'chat-plinko') }}" class="{{ $link }}">Chat Plinko</a>. It's great!
                    </p>
                </div>
            </div>
        </article>

        @include('welcome.footer')
    </div>
</body>
</html>
