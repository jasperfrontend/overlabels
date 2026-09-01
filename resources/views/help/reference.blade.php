@extends('layouts.help')

@section('content')
    <div class="mx-auto max-w-[1240px] px-4 pt-6 pb-20 sm:px-6 sm:pt-8 lg:grid lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start lg:gap-12">

        <details class="help-card mb-6 lg:hidden">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">Browse the reference</summary>
            <div class="px-2 pb-2">
                @include('help._tree', ['groups' => $navGroups])
            </div>
        </details>

        <aside id="help-sidebar" class="sticky top-6 hidden max-h-[calc(100vh-3rem)] overflow-y-auto pr-2 [scrollbar-width:thin] lg:block">
            @include('help._tree', ['groups' => $navGroups])
        </aside>

        <article class="min-w-0 max-w-[760px]">
            @if (!$entry)
                <nav class="mb-4 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground" aria-label="Breadcrumb">
                    <a href="/help" class="cursor-pointer hover:text-foreground">Help</a>
                    <span aria-hidden="true">→</span>
                    <span class="text-foreground">Reference</span>
                </nav>

                <h1 class="mb-3.5 text-3xl font-bold leading-[1.15] tracking-tight sm:text-[34px]">Reference</h1>

                <div class="mb-9 flex flex-wrap items-center gap-x-3.5 gap-y-3 border-b border-sidebar-border pb-6">
                    <span class="help-pill help-pill--reference">Reference</span>
                    <span class="text-[13px] text-muted-foreground">{{ $totalCount }} entries</span>
                    <span class="ml-auto text-xs text-muted-foreground">
                        Don't like my frontend? <abbr title="Bring Your Own Frontend" style="cursor: help;">BYOF</abbr>:
                        <a target="_blank" href="https://overlabels.com/help-reference-index.json" class="cursor-pointer underline">/help-reference-index.json</a>
                    </span>
                </div>

                {{--
                    Body copy about llms.txt, in the article column of the highest-priority
                    page in this section. A <link rel="llms-txt"> in the head is a
                    declaration, not a link a crawler follows, and llms.txt is a convention
                    rather than a ratified standard - so nothing indexes the file on its own.
                    This block, plus /help/llms-txt, is what makes it
                    discoverable. Do not reduce it to a badge or an icon link.
                --}}
                <div class="help-card mb-6 p-6">
                    <h2 class="mb-2 text-lg font-semibold">Using an AI assistant? Start with llms.txt</h2>
                    <p class="text-sm text-foreground">
                        Overlabels publishes its complete overlay-authoring guide as one plain text file at
                        <a href="/llms.txt" class="cursor-pointer font-mono underline">https://overlabels.com/llms.txt</a>.
                        It is written for language models and any of them may read it - no login, no API key, nothing to sign up for.
                        Hand that URL to Claude, ChatGPT, Gemini, Copilot, or a local model and ask for an overlay in plain
                        language.
                    </p>
                    <p class="mt-3 text-sm text-foreground">
                        It covers the hard rules, the three template types, the full
                        <code class="font-mono">[[[tag]]]</code> syntax with pipes and conditionals, every data source, and
                        three complete worked examples. Read it end to end and you can write a working static, alert, or
                        block template without seeing the codebase.
                    </p>
                    <p class="mt-3 text-sm text-foreground">
                        More on what machines can read here:
                        <a href="/help/llms-txt" class="cursor-pointer underline">llms.txt</a>,
                        <a href="/help/markdown-endpoints" class="cursor-pointer underline">markdown endpoints</a>,
                        and <a href="/help/help-reference-index-json" class="cursor-pointer underline">help-reference-index.json</a>.
                    </p>
                </div>

                <div class="help-card p-6">
                    <h2 class="mb-2 text-lg font-semibold">Pick an entry from the sidebar</h2>
                    <p class="text-sm text-foreground">
                        Or start typing in the search box above - it covers tutorials, guides and reference entries at once,
                        so "followe" finds every follower-related tag, event and loop field alongside the guide that explains
                        them. Press <kbd class="help-kbd">Alt</kbd>+<kbd class="help-kbd">R</kbd> to jump to it.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($groups as $group)
                            <button
                                type="button"
                                data-help-search="{{ $group['categoryLabel'] }}"
                                class="help-card help-card--hover cursor-pointer p-3 text-left"
                            >
                                <div class="text-sm font-medium">{{ $group['categoryLabel'] }}</div>
                                <div class="text-xs text-muted-foreground">{{ count($group['items']) }} entries</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <nav class="mb-4 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground" aria-label="Breadcrumb">
                    <a href="/help" class="cursor-pointer hover:text-foreground">Help</a>
                    <span aria-hidden="true">→</span>
                    <a href="/help/reference" class="cursor-pointer hover:text-foreground">Reference</a>
                    <span aria-hidden="true">→</span>
                    <span class="text-foreground">{{ $entry['categoryLabel'] }}</span>
                </nav>

                <h1 class="mb-3.5 break-all font-mono text-2xl font-semibold tracking-tight sm:text-[30px]">{{ $entry['title'] }}</h1>

                <div class="mb-9 flex flex-wrap items-center gap-x-3.5 gap-y-3 border-b border-sidebar-border pb-6">
                    <span class="help-pill help-pill--reference">Reference</span>
                    <span class="text-[13px] text-muted-foreground">{{ $entry['categoryLabel'] }}</span>
                    <a href="/help/reference/{{ $entry['category'] }}/{{ $entry['slug'] }}.md" class="ml-auto cursor-pointer font-mono text-xs text-muted-foreground hover:text-foreground" title="This entry as plain markdown">.md</a>
                </div>

                @if ($tagSnippet)
                    <div class="help-card mb-6 overflow-hidden">
                        <div class="flex items-center justify-between border-b border-sidebar-border px-3 py-1.5 font-mono text-[11px] uppercase tracking-[0.08em] text-muted-foreground">
                            <span>{{ $tagSnippet['label'] }}</span>
                            <button
                                type="button"
                                data-help-copy="{{ $tagSnippet['code'] }}"
                                class="flex cursor-pointer items-center gap-1 rounded px-1.5 py-0.5 text-foreground hover:bg-accent"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3" aria-hidden="true">
                                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                                </svg>
                                <span class="text-[10px]">Copy</span>
                            </button>
                        </div>
                        <pre class="overflow-x-auto whitespace-pre-wrap break-all px-3 py-2 font-mono text-sm text-foreground">{{ $tagSnippet['code'] }}</pre>
                    </div>
                @endif

                <div class="help-prose text-sm text-foreground">
                    {!! $renderedBody !!}
                </div>
            @endif
        </article>
    </div>

    @if (!$entry)
        {{--
            Machine-readable restatement of the block above: names llms.txt as a
            free, plain-text download and points at the page that explains it.

            Built as a PHP array rather than written out as literal JSON because
            `@context` is a real Blade directive (the Context facade). Inline
            JSON-LD gets compiled into PHP and swallows the rest of the file.
            `@type` and `@id` are not directives today, but nothing stops them
            from becoming ones - keep the JSON out of Blade's reach.
        --}}
        @php
            $referenceJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'TechArticle',
                '@id' => 'https://overlabels.com/help/reference',
                'url' => 'https://overlabels.com/help/reference',
                'name' => 'Overlabels Reference',
                'description' => 'Searchable reference for every Overlabels template tag, EventSub event, and foreach loop field.',
                'isAccessibleForFree' => true,
                'hasPart' => [
                    [
                        '@type' => 'DataDownload',
                        'name' => 'llms.txt',
                        'description' => 'The complete Overlabels overlay-authoring guide as one plain text file, written for large language models. Covers template syntax, formatter pipes, conditionals, foreach loops, controls, lists, and event data.',
                        'contentUrl' => 'https://overlabels.com/llms.txt',
                        'encodingFormat' => 'text/plain',
                        'usageInfo' => 'https://overlabels.com/help/llms-txt',
                        'isAccessibleForFree' => true,
                    ],
                    [
                        '@type' => 'DataDownload',
                        'name' => 'help-reference-index.json',
                        'description' => 'This entire reference as one JSON array: every template tag, EventSub event, EventSub tag, and foreach loop field.',
                        'contentUrl' => 'https://overlabels.com/help-reference-index.json',
                        'encodingFormat' => 'application/json',
                        'usageInfo' => 'https://overlabels.com/help/help-reference-index-json',
                        'isAccessibleForFree' => true,
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($referenceJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG) !!}</script>
    @endif
@endsection
