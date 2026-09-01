@extends('layouts.help')

@section('content')
    @php
        $kindGroup = match ($kind) {
            \App\Support\HelpCorpus::KIND_TUTORIAL => ['label' => 'Tutorials', 'url' => '/help#tutorials'],
            \App\Support\HelpCorpus::KIND_DEEP_DIVE => ['label' => 'Deep dives', 'url' => '/help#deep-dives'],
            default => ['label' => 'Guides', 'url' => '/help#guides'],
        };
    @endphp

    <div class="mx-auto max-w-[1240px] px-4 pt-6 pb-20 sm:px-6 sm:pt-8 lg:grid lg:grid-cols-[240px_minmax(0,1fr)] lg:items-start lg:gap-12 xl:grid-cols-[240px_minmax(0,1fr)_200px]">

        {{-- Below lg the tree folds into one row above the article. --}}
        <details class="help-card mb-6 lg:hidden">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">Browse the docs</summary>
            <div class="px-2 pb-2">
                @include('help._tree', ['groups' => $navGroups])
            </div>
        </details>

        <aside id="help-sidebar" class="sticky top-6 hidden max-h-[calc(100vh-3rem)] overflow-y-auto pr-2 [scrollbar-width:thin] lg:block">
            @include('help._tree', ['groups' => $navGroups])
        </aside>

        <article class="min-w-0 max-w-[720px]">
            <nav class="mb-4 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground" aria-label="Breadcrumb">
                <a href="/help" class="cursor-pointer hover:text-foreground">Help</a>
                <span aria-hidden="true">→</span>
                <a href="{{ $kindGroup['url'] }}" class="cursor-pointer hover:text-foreground">{{ $kindGroup['label'] }}</a>
                @if ($kind === \App\Support\HelpCorpus::KIND_GUIDE && $group['label'] !== $kindGroup['label'])
                    <span aria-hidden="true">→</span>
                    <a href="{{ $group['url'] }}" class="cursor-pointer hover:text-foreground">{{ $group['label'] }}</a>
                @endif
            </nav>

            <h1 class="mb-3.5 text-3xl font-bold leading-[1.15] tracking-tight sm:text-[34px]">{{ $heading }}</h1>

            @if (!empty($lead))
                <p class="mb-5 text-base text-muted-foreground">{{ $lead }}</p>
            @endif

            <div class="mb-9 flex flex-wrap items-center gap-x-3.5 gap-y-3 border-b border-sidebar-border pb-6">
                <span class="help-pill help-pill--{{ $kind }}">{{ \App\Support\HelpCorpus::KIND_LABELS[$kind] }}</span>
                <span class="text-[13px] text-muted-foreground">{{ $readingMinutes }} min read</span>
                <div class="ml-auto flex items-center gap-3">
                    {{--
                        Copies the .md twin, not the rendered text: what you get is
                        the file on disk, byte for byte, which is what an assistant
                        wants pasted in.
                    --}}
                    <button type="button" class="help-btn help-btn--sm" data-help-copy-page="{{ $markdownUrl }}">Copy page as Markdown</button>
                    <a href="{{ $markdownUrl }}" class="cursor-pointer font-mono text-xs text-muted-foreground hover:text-foreground" title="This page as plain markdown">.md</a>
                </div>
            </div>

            {{--
                On narrow screens the table of contents folds in above the prose.
                A single-heading page gets none: a one-item list is navigation
                that costs a screenful and saves nothing.
            --}}
            @if (count($toc) > 1)
                <details class="help-card mb-8 xl:hidden">
                    <summary class="cursor-pointer px-4 py-3 font-mono text-[11px] uppercase tracking-[0.08em] text-muted-foreground">On this page</summary>
                    <ol class="flex flex-col px-4 pb-3">
                        @foreach ($toc as $item)
                            <li><a href="#{{ $item['id'] }}" class="help-toc-link cursor-pointer">{{ $item['text'] }}</a></li>
                        @endforeach
                    </ol>
                </details>
            @endif

            {{--
                Server-rendered from markdown. Safe: the source is repo-controlled prose
                written by us, never user input.
            --}}
            <div class="help-prose">
                {!! $html !!}
            </div>

            @if ($prev || $next)
                <nav class="mt-12 grid gap-4 sm:grid-cols-2" aria-label="Nearby pages">
                    @if ($prev)
                        <a href="{{ $prev['url'] }}" class="help-card help-card--hover flex cursor-pointer flex-col gap-0.5 px-[18px] py-3.5">
                            <span class="font-mono text-[11px] text-muted-foreground/70">← Previous</span>
                            <span class="text-sm">{{ $prev['title'] }}</span>
                        </a>
                    @else
                        <span aria-hidden="true"></span>
                    @endif
                    @if ($next)
                        <a href="{{ $next['url'] }}" class="help-card help-card--hover flex cursor-pointer flex-col gap-0.5 px-[18px] py-3.5 text-right">
                            <span class="font-mono text-[11px] text-muted-foreground/70">Next →</span>
                            <span class="text-sm">{{ $next['title'] }}</span>
                        </a>
                    @endif
                </nav>
            @endif

            @if ($related !== [])
                <section class="mt-12">
                    <h2 class="help-section-title mb-4 text-base">Related docs</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        @foreach ($related as $doc)
                            <a href="{{ $doc['url'] }}" class="help-card help-card--hover flex cursor-pointer flex-col gap-2 px-[18px] py-4">
                                <span class="help-pill-text help-pill-text--{{ $doc['kind'] }}">{{ $doc['kindLabel'] }}</span>
                                <span class="text-sm">{{ $doc['title'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <p class="mt-12 border-t border-sidebar-border pt-4 text-xs text-muted-foreground">
                Reading this as a machine? The same page as plain markdown:
                <a href="{{ $markdownUrl }}" class="cursor-pointer font-mono underline">{{ $markdownUrl }}</a>
            </p>
        </article>

        @if (count($toc) > 1)
            <aside class="sticky top-6 hidden xl:block">
                <p class="mb-2.5 font-mono text-[11px] uppercase tracking-[0.08em] text-muted-foreground/70">On this page</p>
                <nav id="help-toc" class="flex flex-col" aria-label="On this page">
                    @foreach ($toc as $item)
                        <a href="#{{ $item['id'] }}" class="help-toc-link cursor-pointer">{{ $item['text'] }}</a>
                    @endforeach
                </nav>
            </aside>
        @endif
    </div>
@endsection
