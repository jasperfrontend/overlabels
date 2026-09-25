{{--
  The shelf. One row per product on the Products shelf of /products, read
  from the recipe catalogue by the home route, so a new manifest shows up
  here without anyone editing this file. A product with a demo partial in
  welcome/demos gets it, running; one without gets its hero artwork. The
  three "what you get" lines per product are the one hand-kept thing here,
  and a product without them shows the manifest's first sentence only.

  Each product owns a whole row: the demo runs at the full content width,
  the description sits below it in two columns (name and pitch on the left,
  what you get and the button on the right). Rows are separated by a thin
  rule and a numbered mono label, never boxed.
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
    ];

    $facts = [
        'chat-tower' => [
            'Chat types !stack, or !stack left and !stack right to fight the lean.',
            'The tower sways more the taller it gets, and the fall lines are on screen so chat sees it coming.',
            'Whoever topples it gets named. Everyone who built the all-time record tower lands in a List any overlay can show.',
        ],
        'follower-bowling' => [
            'Your ten newest followers are the pins, with their avatars and names.',
            '!bowl puts a viewer in line. While the lane is on, it sends the next one down every fifteen seconds by itself.',
            'Ball, pins and score play out on the overlay. A strike says STRIKE.',
        ],
        'chat-checkin' => [
            '!checkin and a city drops a pin on a spinning globe.',
            'Counts every checkin, the countries this stream, and who checked in from farthest away.',
            'A HUD with the numbers and a list of who checked in, each behind one toggle.',
        ],
        'twitch-chat-overlay' => [
            'Names in their Twitch colours, badges, Twitch and third-party emotes, a chip on a first message.',
            'Ten looks, from a terminal to speech bubbles to a news ticker, and thirteen controls for font, colours, layout and how long a message stays.',
            'Nothing to connect and no bot to add. Every change lands in OBS as you make it.',
        ],
    ];
@endphp
<section id="products" class="scroll-mt-16 border-b border-b-sidebar-border bg-card py-20 sm:py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Products</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Pick one. Your chat plays it tonight.</h2>
      <p class="mb-16 max-w-2xl text-lg text-foreground sm:mb-20">
        Every product installs in one click and shows up in OBS as one browser source. Made by Overlabels,
        free, and not available anywhere else. Every demo below runs the product's own rules, with made-up viewers.
      </p>

      <div class="flex flex-col gap-16 sm:gap-24">
        @foreach ($games as $game)
          @php
              $cmd = $command($game['description']);
              $demo = $demos[$game['slug']] ?? null;
              $lines = $facts[$game['slug']] ?? [];
              $number = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
          @endphp
          <article class="{{ $loop->first ? '' : 'border-t border-sidebar-border pt-16 sm:pt-24' }} min-w-0" aria-labelledby="product-{{ $game['slug'] }}">
            {{-- Row label: the number in the same voice as the steps in "How it works". --}}
            <div class="mb-5 flex items-center gap-4">
              <span class="font-mono text-xs text-sky-500">{{ $number }}</span>
              <span class="font-mono text-xs text-muted-foreground">{{ $game['name'] }}</span>
            </div>

            {{-- The demo, at the whole content width. --}}
            <div class="min-w-0">
              @if ($demo)
                @include('welcome.demos.'.$demo)
              @elseif (!empty($game['hero']))
                <img src="{{ $game['hero'] }}" alt="" class="block aspect-video w-full rounded-sm border border-sidebar-border object-cover" width="1280" height="720" loading="lazy" />
              @endif
            </div>

            {{-- The description, below the demo: the pitch left, what you get right. --}}
            <div class="mt-8 grid gap-8 sm:mt-10 md:grid-cols-[1.1fr_1fr] md:gap-12 lg:gap-16">
              <div class="min-w-0">
                @if ($cmd)
                  <span class="mb-4 inline-block border border-violet-400/60 px-2 py-0.5 font-mono text-xs text-violet-600 dark:text-violet-300">chat types {{ $cmd }}</span>
                @else
                  <span class="mb-4 inline-block border border-sidebar-border px-2 py-0.5 text-xs text-muted-foreground">no bot needed</span>
                @endif
                <h3 id="product-{{ $game['slug'] }}" class="mb-4 text-3xl leading-[1.1] font-bold tracking-tight sm:text-4xl">{{ $game['name'] }}</h3>
                <p class="max-w-lg text-lg leading-relaxed text-foreground">{{ $firstSentence($game['description']) }}</p>
              </div>

              <div class="min-w-0 md:pt-1">
                @if ($lines)
                  <ul class="mb-8 flex flex-col gap-3">
                    @foreach ($lines as $line)
                      <li class="flex items-start gap-3 text-base text-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mt-1 h-4 w-4 shrink-0 text-sky-500"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>{{ $line }}</span>
                      </li>
                    @endforeach
                  </ul>
                @endif
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                  <a href="{{ route('products.show', $game['slug']) }}" class="btn btn-primary w-full cursor-pointer sm:w-auto">
                    Get {{ $game['name'] }}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                  </a>
                  <span class="text-xs text-muted-foreground">Free. One click. One link into OBS.</span>
                </div>
              </div>
            </div>
          </article>
        @endforeach
      </div>

      <p class="mt-16 border-t border-sidebar-border pt-10 text-sm text-foreground sm:mt-24">
        More are coming, and the next one comes from what streamers ask for.
        <a href="{{ route('products.index') }}" class="text-sky-500 hover:underline cursor-pointer">See everything on the products page</a>.
      </p>
    </div>
  </div>
</section>
