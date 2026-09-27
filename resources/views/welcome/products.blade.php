{{--
  The teaser shelf. One row per product on the Products shelf of /products,
  read from the recipe catalogue by the home route, so a new manifest shows
  up here without anyone editing this file. A product with a demo partial in
  welcome/demos gets it, running; one without gets its hero artwork. This is
  a teaser, not the pitch: name, first sentence, one button to the product's
  own pitch page (resources/views/products/pitch.blade.php), which is where
  the full "what you get" highlights live now.

  Each product owns a whole row: the demo runs constrained under the row,
  the name/description/button sit below it in one column. Rows are separated
  by a thin rule and a numbered mono label, never boxed.
--}}
@php
    $firstSentence = function (string $text): string {
        $end = strpos($text, '. ');

        return $end === false ? $text : substr($text, 0, $end + 1);
    };
    $command = fn (string $text): ?string => preg_match('/!\w+/', $text, $m) ? $m[0] : null;

    $demos = [
        'chat-tower' => 'tower',
        'follower-bowling' => 'bowling',
        'chat-checkin' => 'checkin',
        'twitch-chat-overlay' => 'chat',
        'chat-emote-bubbles' => 'bubbles',
    ];
@endphp
<section id="products" class="scroll-mt-16 border-b border-b-sidebar-border bg-card py-20 sm:py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Products</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Pick one. Your Twitch chat plays it tonight.</h2>
      <p class="mb-16 max-w-2xl text-lg text-foreground sm:mb-20">
        Every product installs in one click and shows up on your Twitch stream as one browser source in OBS. Made by
        Overlabels, free, and not available anywhere else. Every demo below runs the product's own rules, with made-up
        Twitch viewers.
      </p>

      <div class="flex flex-col gap-16 sm:gap-24">
        @foreach ($games as $game)
          @php
              $cmd = $command($game['description']);
              $demo = $demos[$game['slug']] ?? null;
              $number = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
          @endphp
          <article class="{{ $loop->first ? '' : 'border-t border-sidebar-border pt-16 sm:pt-24' }} min-w-0" aria-labelledby="product-{{ $game['slug'] }}">
            {{-- Row label: the number in the same voice as the steps in "How it works". --}}
            <div class="mb-5 flex items-center gap-4">
              <span class="font-mono text-xs text-sky-500">{{ $number }}</span>
              <span class="font-mono text-xs text-muted-foreground">{{ $game['name'] }}</span>
            </div>

            {{-- The demo, constrained rather than full width: this is a teaser, the pitch page is where it runs at full size. --}}
            <div class="mx-auto min-w-0 max-w-2xl">
              @if ($demo)
                @include('welcome.demos.'.$demo)
              @elseif (!empty($game['hero']))
                <img src="{{ $game['hero'] }}" alt="" class="block aspect-video w-full rounded-sm border border-sidebar-border object-cover" width="1280" height="720" loading="lazy" />
              @endif
            </div>

            <div class="mx-auto mt-8 flex max-w-2xl min-w-0 flex-col items-start gap-4 sm:mt-10">
              @if ($cmd)
                <span class="inline-block border border-violet-400/60 px-2 py-0.5 font-mono text-xs text-violet-600 dark:text-violet-300">Twitch chat types {{ $cmd }}</span>
              @else
                <span class="inline-block border border-sidebar-border px-2 py-0.5 text-xs text-muted-foreground">no bot needed</span>
              @endif
              <h3 id="product-{{ $game['slug'] }}" class="text-3xl leading-[1.1] font-bold tracking-tight sm:text-4xl">{{ $game['name'] }}</h3>
              <p class="max-w-lg text-lg leading-relaxed text-foreground">{{ $firstSentence($game['description']) }}</p>
              <a href="{{ route('products.show', $game['slug']) }}" class="btn btn-primary w-full cursor-pointer sm:w-auto">
                Get {{ $game['name'] }}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
              </a>
            </div>
          </article>
        @endforeach
      </div>

      <p class="mt-16 border-t border-sidebar-border pt-10 text-sm text-foreground sm:mt-24">
        More are coming, and the next one comes from what Twitch streamers ask for.
        <a href="{{ route('products.index') }}" class="text-sky-500 hover:underline cursor-pointer">See everything on the products page</a>.
      </p>
    </div>
  </div>
</section>
