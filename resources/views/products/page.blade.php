@include('products._head')
<body class="font-sans antialiased">
    <div class="min-h-screen bg-sidebar-accent text-foreground">
        @include('welcome.navbar')

        @php
            // Hand-kept, same as pitch.blade.php and welcome/products.blade.php:
            // a product with a demo partial gets it running, one without falls
            // back to its hero image.
            $demos = [
                'chat-tower' => 'tower',
                'follower-bowling' => 'bowling',
                'chat-checkin' => 'checkin',
                'twitch-chat-overlay' => 'chat',
                'chat-emote-bubbles' => 'bubbles',
            ];
            $demo = $demos[$product['slug']] ?? null;

            // Manifest text may wrap a command in backticks and carry a
            // [text](url) link to an https:// or site-relative address; that is
            // all the markup it has. Escape first, so nothing else in it can be
            // HTML (e() also turns a quote in the url into &quot;, so it cannot
            // leave the attribute). An off-site link opens in a new tab.
            $md = fn (string $text): string => preg_replace_callback(
                '/\[([^\]]+)\]\((https:\/\/[^\s)]+|\/[^\s)]*)\)/',
                fn (array $m) => '<a href="'.$m[2].'" class="cursor-pointer text-sky-500 hover:underline"'
                    .(str_starts_with($m[2], 'https://') ? ' target="_blank" rel="noopener noreferrer"' : '')
                    .'>'.$m[1].'</a>',
                preg_replace('/`([^`]+)`/', '<code class="pp-code">$1</code>', e($text)),
            );

            $cta = $installed ? 'Manage '.$product['name'] : 'Get '.$product['name'];
            $ctaUrl = route('products.manage', $product['slug']);

            $icons = [
                'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
                'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
                'people' => '<circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><path d="M15.5 14.6c2.6-.3 4.9 1.3 5.8 4.4"/>',
                'shield' => '<path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
                'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
                'palette' => '<path d="M12 3a9 9 0 0 0 0 18c1.1 0 1.7-.9 1.4-1.9-.4-1.2.4-2.1 1.6-2.1H17a4 4 0 0 0 4-4c0-5.5-4-10-9-10z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
                'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4"/><path d="M16 6h3a3 3 0 0 1-3 4"/><path d="M12 13v4"/><path d="M8 20h8"/>',
                'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
            ];
            $arrow = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
        @endphp

        {{-- 1. Hero: the promise and the button first, then the product running. --}}
        <section class="border-b border-b-sidebar-border bg-card py-14 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <div class="mb-10 max-w-3xl">
                        <p class="pp-kicker">{{ $product['name'] }}</p>
                        <h1 class="mb-5 text-4xl leading-[1.05] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $page['tagline'] }}</h1>
                        <p class="mb-7 max-w-2xl text-lg leading-relaxed text-foreground sm:text-xl">{!! $md($page['lede']) !!}</p>
                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-5">
                            <a href="{{ $ctaUrl }}" class="btn btn-primary w-full cursor-pointer sm:w-auto">{{ $cta }}{!! $arrow !!}</a>
                            @unless ($installed)
                                <span class="text-sm text-muted-foreground">Free. {{ ucfirst($page['steps_word']) }} steps.</span>
                            @endunless
                        </div>
                        @include('products._works-with')
                    </div>

                    @if ($demo)
                        @include('welcome.demos.'.$demo, ['bare' => true])
                    @elseif (!empty($product['hero']))
                        <img src="{{ $product['hero'] }}" alt="" class="block aspect-video w-full rounded-sm border border-sidebar-border object-cover" width="1280" height="720" loading="lazy" />
                    @endif
                </div>
            </div>
        </section>

        {{-- 2. What chat does: three beats, the pictures carry them. --}}
        <section class="border-b border-b-sidebar-border py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <p class="pp-kicker">What your chat does</p>
                    <h2 class="mb-10 text-3xl font-bold tracking-tight text-balance sm:text-4xl">One command. Three things happen.</h2>
                    <ol class="pp-flow">
                        @foreach ($page['plays'] as $i => $play)
                            <li class="pp-beat">
                                <span class="pp-beat-n" aria-hidden="true">{{ $i + 1 }}</span>
                                <div class="pp-art">
                                    @if ($play['art']['type'] === 'chat')
                                        <div class="pp-chat">
                                            @foreach ($play['art']['lines'] as $j => $line)
                                                <div @class(['pp-chat-dim' => $j < count($play['art']['lines']) - 1])><span class="pp-chat-name" style="color: {{ $line['color'] ?? '#a78bfa' }}">{{ $line['name'] }}</span>: {{ $line['text'] }}@if ($j === count($play['art']['lines']) - 1)<span class="pp-caret"></span>@endif</div>
                                            @endforeach
                                        </div>
                                    @elseif ($play['art']['type'] === 'stat')
                                        <div class="pp-stat">
                                            @foreach ($play['art']['stats'] as $stat)
                                                <div><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
                                            @endforeach
                                            @if (!empty($play['art']['caption']))
                                                <p>{{ $play['art']['caption'] }}</p>
                                            @endif
                                        </div>
                                    @else
                                        <img src="{{ $play['art']['src'] }}" alt="{{ $play['art']['alt'] ?? '' }}" width="240" height="150" loading="lazy" />
                                    @endif
                                </div>
                                <h3 class="mb-1.5 text-xl font-bold tracking-tight">{!! $md($play['title']) !!}</h3>
                                <p class="text-base text-muted-foreground">{!! $md($play['line']) !!}</p>
                            </li>
                        @endforeach
                    </ol>
                    @if ($page['plays_note'])
                        <p class="mt-8 text-[15px] text-muted-foreground">
                            {!! $md($page['plays_note']['text']) !!}
                            @if ($page['plays_note']['link_url'])
                                <a href="{{ $page['plays_note']['link_url'] }}" class="text-violet-600 underline underline-offset-2 dark:text-violet-300">{{ $page['plays_note']['link_label'] }}</a>.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Why: what the product is, plainly. --}}
        <section class="border-b border-b-sidebar-border bg-card py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <p class="pp-kicker">Why streamers use it</p>
                    <h2 class="mb-10 text-3xl font-bold tracking-tight text-balance sm:text-4xl">{{ $page['reasons_heading'] }}</h2>
                    <div class="pp-reasons">
                        @foreach ($page['reasons'] as $reason)
                            <div>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-3.5 h-[22px] w-[22px] text-teal-600 dark:text-teal-400" aria-hidden="true">{!! $icons[$reason['icon']] !!}</svg>
                                <h3 class="mb-2 text-xl font-bold tracking-tight">{!! $md($reason['title']) !!}</h3>
                                <p class="text-muted-foreground">{!! $md($reason['body']) !!}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. Looks: the manifest's own, or the designer's presets. --}}
        @if ($page['looks'])
            <section class="border-b border-b-sidebar-border py-16 sm:py-20">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-5xl">
                        <p class="pp-kicker">Make it yours</p>
                        <h2 class="mb-3 text-3xl font-bold tracking-tight text-balance sm:text-4xl">{{ $page['looks_heading'] ?? 'Make it look like your stream.' }}</h2>
                        @if (!empty($page['looks_lede']))
                            <p class="mb-10 max-w-2xl text-lg text-muted-foreground">{{ $page['looks_lede'] }}</p>
                        @endif
                        <div @class(['pp-looks', 'pp-looks--text' => empty($page['looks'][0]['image'])])>
                            @foreach ($page['looks'] as $look)
                                <div class="pp-look">
                                    @if (!empty($look['image']))
                                        <img src="{{ $look['image'] }}" alt="" width="320" height="200" loading="lazy" />
                                    @endif
                                    <div class="pp-look-body">
                                        <h3 class="mb-1 text-[17px] font-bold">{{ $look['title'] }}@if (!empty($look['badge']))<span class="pp-badge">{{ $look['badge'] }}</span>@endif</h3>
                                        <p class="text-[15px] text-muted-foreground">{{ $look['body'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- 5. Setup: the steps are derived, not written per product. --}}
        <section class="border-b border-b-sidebar-border bg-card py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <p class="pp-kicker">Ready in {{ $page['steps_word'] }} steps</p>
                    <h2 class="mb-3 text-3xl font-bold tracking-tight text-balance sm:text-4xl">From this page to your stream.</h2>
                    <p class="mb-10 max-w-2xl text-lg text-muted-foreground">Every step is a button on the next page. You never have to go looking for a setting.</p>
                    <ol @class(['pp-setup', 'pp-setup--two' => count($page['steps']) === 2])>
                        @foreach ($page['steps'] as $i => $step)
                            <li>
                                <span class="pp-setup-n">{{ $i + 1 }}</span>
                                <h3 class="mb-1.5 text-lg font-bold">{{ $step['title'] }}</h3>
                                <p class="text-[15px] text-muted-foreground">{!! $md($step['body']) !!}</p>
                            </li>
                        @endforeach
                    </ol>
                    @if (!empty($page['setup_note']))
                        <p class="mt-5 text-[15px] text-muted-foreground">{!! $md($page['setup_note']) !!}</p>
                    @endif
                </div>
            </div>
        </section>

        {{-- 6. Questions: native details, no script. --}}
        <section class="border-b border-b-sidebar-border py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <p class="pp-kicker">Questions</p>
                    <h2 class="mb-8 text-3xl font-bold tracking-tight text-balance sm:text-4xl">Before you click.</h2>
                    <div class="pp-faq">
                        @foreach ($page['faq'] as $i => $item)
                            <details @if ($i === 0) open @endif>
                                <summary>{!! $md($item['q']) !!}</summary>
                                <p>{!! $md($item['a']) !!}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- 7. Closing: the button again, and where to go next. --}}
        <section class="border-b border-b-sidebar-border bg-card py-16 sm:py-20">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    <div class="grid items-center gap-6 md:grid-cols-[1.3fr_1fr]">
                        <div class="min-w-0">
                            <h2 class="mb-2 text-3xl font-bold tracking-tight text-balance sm:text-4xl">{{ $page['closing'] }}</h2>
                            @unless ($installed)
                                <p class="text-base text-muted-foreground">Free. {{ ucfirst($page['steps_word']) }} steps.</p>
                            @endunless
                            @include('products._works-with')
                        </div>
                        <div class="min-w-0">
                            <a href="{{ $ctaUrl }}" class="btn btn-primary w-full cursor-pointer sm:w-auto">{{ $cta }}{!! $arrow !!}</a>
                        </div>
                    </div>

                    @if ($page['more'])
                        <p class="pp-kicker pp-kicker--muted mt-14">More things your chat can do</p>
                        <div class="pp-more">
                            @foreach ($page['more'] as $other)
                                <a href="{{ $other['url'] }}">
                                    <strong>{{ $other['name'] }}</strong>
                                    <span>{{ $other['tagline'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @include('welcome.footer')
    </div>
</body>
</html>
