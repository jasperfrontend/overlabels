@extends('layouts.help')

@section('content')
    @if (!$entry)
        <div class="mb-6 flex flex-wrap items-baseline gap-3">
            <h1 class="text-2xl font-bold">Reference</h1>
            <span class="text-sm text-muted-foreground">{{ $totalCount }} entries</span>
            <span class="ml-auto text-xs text-muted-foreground">
                Don't like my frontend? <abbr title="Bring Your Own Frontend" style="cursor: help;">BYOF</abbr>:
                <a target="_blank" href="https://overlabels.com/help-reference-index.json" class="underline cursor-pointer">/help-reference-index.json</a>
            </span>
        </div>

        {{--
            Body copy about llms.txt, in the article column of the highest-priority
            page in this section. A <link rel="llms-txt"> in the head is a
            declaration, not a link a crawler follows, and llms.txt is a convention
            rather than a ratified standard - so nothing indexes the file on its own.
            This block, plus /help/reference/for-machines/llms-txt, is what makes it
            discoverable. Do not reduce it to a badge or an icon link.
        --}}
        <div class="mb-6 border border-sidebar-border p-6">
            <h2 class="mb-2 text-lg font-semibold">Using an AI assistant? Start with llms.txt</h2>
            <p class="text-sm text-foreground">
                Overlabels publishes its complete overlay-authoring guide as one plain text file at
                <a href="/llms.txt" class="font-mono underline cursor-pointer">https://overlabels.com/llms.txt</a>.
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
                <a href="/help/reference/for-machines/llms-txt" class="underline cursor-pointer">llms.txt</a>,
                <a href="/help/reference/for-machines/markdown-endpoints" class="underline cursor-pointer">markdown endpoints</a>,
                and <a href="/help/reference/for-machines/help-reference-index-json" class="underline cursor-pointer">help-reference-index.json</a>.
            </p>
        </div>

        <div class="border border-sidebar-border p-6">
            <h2 class="mb-2 text-lg font-semibold">Pick an entry from the sidebar</h2>
            <p class="text-sm text-foreground">
                Or start typing in the search box above - it covers tutorials, guides and reference entries at once,
                so "followe" finds every follower-related tag, event and loop field alongside the guide that explains
                them. Press <kbd class="rounded border border-sidebar-border px-1">Alt</kbd>+<kbd class="rounded border border-sidebar-border px-1">R</kbd> to jump to it.
            </p>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($groups as $group)
                    <button
                        type="button"
                        data-help-search="{{ $group['categoryLabel'] }}"
                        class="border border-sidebar-border p-3 text-left cursor-pointer hover:bg-card"
                    >
                        <div class="font-medium text-sm">{{ $group['categoryLabel'] }}</div>
                        <div class="text-xs text-muted-foreground">{{ count($group['items']) }} entries</div>
                    </button>
                @endforeach
            </div>
        </div>
    @else
        <div class="border border-sidebar-border p-6">
            <div class="mb-4 flex items-center gap-3">
                <span class="text-[10px] uppercase tracking-wide text-muted-foreground/70">
                    {{ $entry['categoryLabel'] }}
                </span>
            </div>
            <h1 class="mb-4 font-mono text-2xl font-semibold break-all">{{ $entry['title'] }}</h1>

            @if ($tagSnippet)
                <div class="mb-5 border border-sidebar-border bg-sidebar-accent/40">
                    <div class="flex items-center justify-between border-b border-b-sidebar-border px-3 py-1.5 text-[11px] uppercase tracking-wide text-foreground">
                        <span>{{ $tagSnippet['label'] }}</span>
                        <button
                            type="button"
                            data-help-copy="{{ $tagSnippet['code'] }}"
                            class="flex items-center gap-1 rounded px-1.5 py-0.5 text-foreground cursor-pointer hover:bg-accent"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3" aria-hidden="true">
                                <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                            </svg>
                            <span class="text-[10px]">Copy</span>
                        </button>
                    </div>
                    <pre class="overflow-x-auto px-3 py-2 font-mono text-sm text-foreground whitespace-pre-wrap break-all">{{ $tagSnippet['code'] }}</pre>
                </div>
            @endif

            <div class="help-prose text-sm text-foreground">
                {!! $renderedBody !!}
            </div>
        </div>
    @endif

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
                        'usageInfo' => 'https://overlabels.com/help/reference/for-machines/llms-txt',
                        'isAccessibleForFree' => true,
                    ],
                    [
                        '@type' => 'DataDownload',
                        'name' => 'help-reference-index.json',
                        'description' => 'This entire reference as one JSON array: every template tag, EventSub event, EventSub tag, and foreach loop field.',
                        'contentUrl' => 'https://overlabels.com/help-reference-index.json',
                        'encodingFormat' => 'application/json',
                        'usageInfo' => 'https://overlabels.com/help/reference/for-machines/help-reference-index-json',
                        'isAccessibleForFree' => true,
                    ],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($referenceJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG) !!}</script>
    @endif
@endsection
