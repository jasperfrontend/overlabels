@extends('layouts.help')

@section('content')
    <div class="mb-10">
        {{-- Guides are the default kind and carry no badge; tutorials and deep dives name themselves. --}}
        @if ($kind !== \App\Support\HelpCorpus::KIND_GUIDE)
            <span class="mb-3 inline-block border border-sidebar-border px-2 py-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">
                {{ \App\Support\HelpCorpus::KIND_LABELS[$kind] }}
            </span>
        @endif

        <h1 class="mb-4 text-4xl font-bold">{{ $heading }}</h1>

        @if (!empty($lead))
            <p class="text-foreground">{{ $lead }}</p>
        @endif
    </div>

    {{--
        A single-heading page gets no table of contents: a one-item list is
        navigation that costs a screenful and saves nothing.
    --}}
    @if (count($toc) > 1)
        <nav class="mb-12 border border-sidebar-border bg-card p-6">
            <h2 id="toc" class="mb-4 text-xl font-bold">Table of contents</h2>
            <ol class="list-decimal space-y-1 pl-6 text-foreground">
                @foreach ($toc as $item)
                    <li>
                        <a href="#{{ $item['id'] }}" class="cursor-pointer text-violet-400 hover:underline">{{ $item['text'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    {{--
        Server-rendered from markdown. Safe: the source is repo-controlled prose
        written by us, never user input.
    --}}
    <div class="help-prose">
        {!! $html !!}
    </div>

    <div class="mt-12 border-t border-sidebar-border pt-4 text-xs text-muted-foreground">
        Reading this as a machine? The same page as plain markdown:
        <a href="{{ $markdownUrl }}" class="font-mono underline cursor-pointer">{{ $markdownUrl }}</a>
    </div>
@endsection
