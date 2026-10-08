{{--
  The teaser shelf. One row per product on the Products shelf of /products,
  read from the recipe catalogue by the home route, so a new manifest shows
  up here without anyone editing this file. A product with a demo partial in
  welcome/demos gets it, running; one without gets its hero artwork. This is
  a teaser, not the pitch: name, first sentence, one button to the product's
  own pitch page (resources/views/products/pitch.blade.php), which is where
  the full "what you get" highlights live now.

  Each row puts the demo (bare: no pretend OBS title bar) left and the copy
  right, the same way round every row so the eye never has to switch sides,
  and carries the product's slug as its anchor: the hero cards and the jump
  bar both link to it. The jump bar sticks under the site nav for as long as
  the rows are on screen (sticky inside their wrapper, so it lets go after
  the last one), and wireProductNav in welcome/app.ts marks the row in view.
--}}
@php
    $firstSentence = function (string $text): string {
        $end = strpos($text, '. ');

        return $end === false ? $text : substr($text, 0, $end + 1);
    };

    $demos = [
        'chat-tower' => 'tower',
        'follower-bowling' => 'bowling',
        'chat-checkin' => 'checkin',
        'twitch-chat-overlay' => 'chat',
        'chat-emote-bubbles' => 'bubbles',
    ];
@endphp
<section id="products" class="scroll-mt-(--ol-nav-h) border-b border-b-sidebar-border bg-card py-20 sm:py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Products</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Pick one. Your Twitch chat plays it tonight.</h2>
      <p class="mb-10 max-w-2xl text-lg text-foreground sm:mb-12">
        Every product installs in one click and shows up on your Twitch stream as one browser source in OBS. Made by
        Overlabels, free, and not available anywhere else. Every demo below runs the product's own rules, with made-up
        Twitch viewers.
      </p>

      <div>
        <nav data-product-nav aria-label="Jump to a product" class="sticky top-(--ol-nav-h) z-40 -mx-4 mb-12 bg-card/90 px-4 backdrop-blur-lg sm:mx-0 sm:mb-16 sm:px-0">
          <ul class="relative flex gap-1 overflow-x-auto py-2 [scrollbar-width:none]">
            @foreach ($games as $game)
              <li class="shrink-0">
                <a href="#{{ $game['slug'] }}" data-product-nav-link="{{ $game['slug'] }}" class="block cursor-pointer rounded-sm px-3 py-1.5 text-sm whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground aria-[current=true]:bg-sky-500/10 aria-[current=true]:text-sky-500">{{ $game['name'] }}</a>
              </li>
            @endforeach
          </ul>
        </nav>

        <div class="flex flex-col gap-[calc(8rem+5vh)] sm:gap-[calc(12rem+8vh)]">
          @foreach ($games as $game)
            @php
                $cmd = $command($game['description']);
                $demo = $demos[$game['slug']] ?? null;
                $number = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
            @endphp
            <article id="{{ $game['slug'] }}" data-product-row class="grid scroll-mt-[calc(var(--ol-nav-h)+5rem)] min-w-0 items-center gap-8 lg:grid-cols-[1.25fr_1fr] lg:gap-14 [&>*]:min-w-0" aria-labelledby="product-{{ $game['slug'] }}-name">
              <div>
                @if ($demo)
                  @include('welcome.demos.'.$demo, ['bare' => true])
                @elseif (!empty($game['hero']))
                  <img src="{{ $game['hero'] }}" alt="" class="block aspect-video w-full rounded-sm border border-sidebar-border object-cover" width="1280" height="720" loading="lazy" />
                @endif
              </div>

              <div class="flex flex-col items-start gap-4">
                {{-- Row label: the number in the same voice as the steps in "How it works". --}}
                <span class="font-mono text-xs text-sky-500">{{ $number }}</span>
                @if ($cmd)
                  <span class="inline-block border border-violet-400/60 px-2 py-0.5 font-mono text-xs text-violet-600 dark:text-violet-300">Twitch chat types {{ $cmd }}</span>
                @else
                  <span class="inline-block border border-sidebar-border px-2 py-0.5 text-xs text-muted-foreground">no bot needed</span>
                @endif
                <h3 id="product-{{ $game['slug'] }}-name" class="text-3xl leading-[1.1] font-bold tracking-tight sm:text-4xl">{{ $game['name'] }}</h3>
                <p class="text-lg leading-relaxed text-foreground">{{ $firstSentence($game['description']) }}</p>
                <a href="{{ route('products.show', $game['slug']) }}" class="btn btn-primary mt-2 w-full cursor-pointer sm:w-auto">
                  Get {{ $game['name'] }}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
              </div>
            </article>
          @endforeach
        </div>
      </div>

      <p class="mt-24 text-sm text-foreground sm:mt-32">
        More are coming, and the next one comes from what Twitch streamers ask for.
        <a href="{{ route('products.index') }}" class="text-sky-500 hover:underline cursor-pointer">See everything on the products page</a>.
      </p>
    </div>
  </div>
</section>
