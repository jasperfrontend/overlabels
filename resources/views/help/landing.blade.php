@extends('layouts.help')

@section('content')
    @php
        $guideCount = array_sum(array_map(fn (array $s): int => count($s['items']), $sections));
        $docCount = count($tutorials) + $guideCount + count($deepDives);
    @endphp

    <header class="mx-auto flex max-w-[720px] flex-col items-center gap-4 px-4 pt-16 pb-14 text-center sm:px-6 sm:pt-[88px] sm:pb-[72px]">
        <h1 class="text-4xl font-bold leading-[1.15] tracking-tight sm:text-5xl">{{ $heading }}</h1>
        @if (!empty($lead))
            <p class="max-w-[480px] text-base text-muted-foreground">{{ $lead }}</p>
        @endif
        <div class="mt-3 w-full max-w-[600px]">
            @include('help._search', ['compact' => false])
        </div>
        <p class="font-mono text-xs text-muted-foreground/70">
            {{ $docCount }} docs · {{ count($tutorials) }} tutorials · {{ $guideCount }} guides · {{ count($deepDives) }} deep dives · {{ $referenceCount }} reference entries
        </p>
    </header>

    <div class="mx-auto flex max-w-[1120px] flex-col gap-6 px-4 pb-20 sm:px-6">

        <div class="grid gap-6 md:grid-cols-2">
            <section id="tutorials" class="help-card scroll-mt-6 p-6 sm:px-7">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="help-pill help-pill--tutorial">Tutorials</span>
                    <span class="help-count">{{ count($tutorials) }} docs</span>
                </div>
                <p class="mb-3.5 text-sm text-muted-foreground">Build something real, start to finish. Each one ends with a working overlay in OBS.</p>
                <div class="-mx-2 flex flex-col">
                    @foreach ($tutorials as $doc)
                        <a href="{{ $doc['url'] }}" class="help-doc-link help-doc-link--row"><span>{{ $doc['title'] }}</span><span class="help-doc-link-arrow" aria-hidden="true">→</span></a>
                    @endforeach
                </div>
            </section>

            <section id="deep-dives" class="help-card scroll-mt-6 p-6 sm:px-7">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="help-pill help-pill--deep-dive">Deep dives</span>
                    <span class="help-count">{{ count($deepDives) }} docs</span>
                </div>
                <p class="mb-3.5 text-sm text-muted-foreground">Teardowns of finished overlays, tag by tag. For when you want to see how far the template language stretches.</p>
                <div class="-mx-2 flex flex-col">
                    @foreach ($deepDives as $doc)
                        <a href="{{ $doc['url'] }}" class="help-doc-link help-doc-link--row"><span>{{ $doc['title'] }}</span><span class="help-doc-link-arrow" aria-hidden="true">→</span></a>
                    @endforeach
                </div>
            </section>
        </div>

        {{--
            One card per section, each with an icon tile and a line saying what
            the group is for - so the guides read as seven doors rather than
            one wall of titles. The first section is the way in, so it gets the
            width of two. Links run in index.md order, not alphabetical.
        --}}
        <section id="guides" class="mt-4 scroll-mt-6">
            <div class="mb-1.5 flex items-center justify-between px-1">
                <span class="help-pill help-pill--guide">Guides</span>
                <span class="help-count">{{ $guideCount }} docs</span>
            </div>
            <p class="mb-4 px-1 text-sm text-muted-foreground">How each part of the engine works. Skim the group you need, ignore the rest.</p>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($sections as $section)
                    <div id="{{ $section['anchor'] }}" @class(['help-card flex scroll-mt-6 flex-col p-5', 'sm:col-span-2' => $loop->first])>
                        <div class="mb-3 flex items-start gap-3">
                            <span class="help-section-icon" aria-hidden="true">@include('help._section-icon', ['label' => $section['label']])</span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-sm leading-tight font-semibold">{{ $section['label'] }}</h2>
                                <p class="mt-1 text-xs leading-snug text-muted-foreground">{{ $section['description'] }}</p>
                            </div>
                            <span class="help-count">{{ count($section['items']) }}</span>
                        </div>
                        <ul @class(['-mx-2 flex flex-col', 'sm:grid sm:grid-cols-2 sm:gap-x-4' => $loop->first])>
                            @foreach ($section['items'] as $doc)
                                <li><a href="{{ $doc['url'] }}" class="help-doc-link help-doc-link--row"><span>{{ $doc['title'] }}</span><span class="help-doc-link-arrow" aria-hidden="true">→</span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="reference" class="help-card scroll-mt-6 p-6 sm:px-7">
            <div class="mb-1.5 flex items-center justify-between">
                <span class="help-pill help-pill--reference">Reference</span>
                <span class="help-count">{{ $referenceCount }} entries</span>
            </div>
            <p class="mb-3.5 text-sm text-muted-foreground">
                Every template tag, EventSub event and foreach field, one page each. The search box above covers all of it, and
                <kbd class="help-kbd">Alt</kbd>+<kbd class="help-kbd">R</kbd> jumps to it from any help page.
            </p>
            <div class="-mx-2 flex flex-col">
                <a href="/help/reference" class="help-doc-link help-doc-link--row"><span>Browse the reference</span><span class="help-doc-link-arrow" aria-hidden="true">→</span></a>
            </div>
        </section>

    </div>
@endsection
