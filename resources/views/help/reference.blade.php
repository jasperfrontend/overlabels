@extends('layouts.help')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-6 text-muted-foreground" aria-hidden="true">
            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a2.5 2.5 0 0 1 0-5H20"/>
        </svg>
        <h1 class="text-2xl font-bold">Reference</h1>
        <span class="text-sm text-muted-foreground">{{ $totalCount }} entries</span>
        <span class="ml-auto text-xs text-muted-foreground hidden sm:inline">
            Tip: press <kbd class="border rounded px-1">Alt</kbd>+<kbd class="border rounded px-1">R</kbd> to focus search
        </span>
    </div>

    <div class="relative mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input
            id="help-reference-search"
            type="text"
            placeholder="Search everything (e.g. follower, raid, hype train)..."
            class="w-full py-2 pl-9 pr-9 text-sm input-border"
            autocomplete="off"
        />
        <button
            id="help-reference-search-clear"
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
            id="help-reference-sidebar"
            class="max-h-[calc(100vh-12rem)] overflow-y-auto border border-sidebar-border p-2"
        >
            <div id="help-reference-tree">
                @foreach ($groups as $group)
                    <div class="px-2 pt-2 pb-1 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">
                        {{ $group['categoryLabel'] }}
                        <span class="ml-1 normal-case font-normal text-muted-foreground/50">({{ count($group['items']) }})</span>
                    </div>
                    @foreach ($group['items'] as $item)
                        @php
                            $isActive = $entry
                                && $entry['category'] === $item['category']
                                && $entry['slug'] === $item['slug'];
                        @endphp
                        <a
                            href="/help/reference/{{ $item['category'] }}/{{ $item['slug'] }}"
                            class="block rounded-md px-2 py-1 font-mono text-xs cursor-pointer hover:bg-sidebar-accent {{ $isActive ? 'bg-card text-violet-400' : 'text-foreground' }}"
                        >{{ $item['title'] }}</a>
                    @endforeach
                @endforeach
            </div>
            <div id="help-reference-results" class="hidden"></div>
        </aside>

        <article class="min-w-0">
            @if (!$entry)
                <div class="border border-sidebar-border p-6">
                    <h2 class="mb-2 text-lg font-semibold">Pick an entry from the sidebar</h2>
                    <p class="text-sm text-foreground">
                        Or start typing above. The search looks through titles, slugs, and body text - so "followe" finds every
                        follower-related tag, event, and loop field at once.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($groups as $group)
                            <button
                                type="button"
                                data-help-search="{{ $group['categoryLabel'] }}"
                                class="border border-sidebar-border p-3 text-left cursor-pointer hover:bg-accent"
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
                    <h2 class="mb-4 font-mono text-2xl font-semibold break-all">{{ $entry['title'] }}</h2>

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
        </article>
    </div>
@endsection
