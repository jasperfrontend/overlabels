{{--
  The hero sells the products, not the syntax. A streamer arriving here should
  read one sentence and know what this is for; the code lives further down,
  under the "Build your own" fold. Under the copy sits one card per product,
  every one of them a link to that product's row on this page: the visitor
  sees the whole range at once and is one click from any of it. The cards are
  read from the same catalogue as the rows, and the grid fills itself, so a
  sixth product needs no layout change.
--}}
<section class="border-b border-sidebar-accent pt-20 pb-16 sm:pt-28 sm:pb-24">
  {{-- Wider than the rest of the page from lg up (90% of the window), so the product cards get the room. --}}
  <div class="mx-auto px-4 sm:px-6 lg:w-[90%] lg:px-0">
    <div class="mx-auto max-w-6xl lg:max-w-none">

      <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:gap-16">
      <div class="max-w-3xl">
        <h1 class="mb-6 text-4xl leading-[1.05] font-bold tracking-tight sm:text-5xl lg:text-6xl">
          <span class="whitespace-nowrap">Twitch Chat Games</span> for Downtime and BRB Breaks
        </h1>

        <p class="mb-10 max-w-2xl text-xl leading-relaxed text-foreground">
          Free Twitch chat games your viewers play by typing a !command in chat, plus a <a href="#twitch-chat-overlay" class="cursor-pointer text-sky-500 hover:underline">Twitch chat overlay</a> you can
          style any way you like in just a few clicks. Put a quick <a href="#chat-tower" class="cursor-pointer text-sky-500 hover:underline">Chat Tower</a> or <a href="#follower-bowling" class="cursor-pointer text-sky-500 hover:underline">Follower Bowling</a> game up while you grab a drink,
          and chat has something to do until you're back.
        </p>

        <div class="flex flex-col items-start gap-4">
          <a href="#products" class="btn btn-primary btn-xl w-full cursor-pointer sm:w-auto sm:px-20">
            Browse Free Twitch Chat Games
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-5 w-5"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
          </a>
        </div>
      </div>

      {{-- Where it runs, and who made it. Three short lines under the buttons on
           a phone; on desktop it fills the hero's right half. StreamElements
           works because an Overlabels overlay is a plain browser source:
           nothing in it talks to StreamElements. --}}
      <x-brand-icons title="Every Overlabels product works with" :icons="['twitch', 'obs', 'streamlabs', 'streamelements']" size="lg" align="left" align-lg="center">
        <x-slot:footer>
          <p class="text-sm text-balance text-foreground lg:max-w-md lg:text-base">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 inline-block size-5 -translate-y-px align-middle text-green-500" aria-hidden="true"><path d="M19.414 14.414C21 12.828 22 11.5 22 9.5a5.5 5.5 0 0 0-9.591-3.676.6.6 0 0 1-.818.001A5.5 5.5 0 0 0 2 9.5c0 2.3 1.5 4 3 5.5l5.535 5.362a2 2 0 0 0 2.879.052 2.12 2.12 0 0 0-.004-3 2.124 2.124 0 1 0 3-3 2.124 2.124 0 0 0 3.004 0 2 2 0 0 0 0-2.828l-1.881-1.882a2.41 2.41 0 0 0-3.409 0l-1.71 1.71a2 2 0 0 1-2.828 0 2 2 0 0 1 0-2.828l2.823-2.762"/></svg>
            Always 100% free and <a href="https://github.com/jasperfrontend/overlabels" class="cursor-pointer text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">open source</a>, no registration required.
            Created by <a href="https://www.twitch.tv/jasperdiscovers" class="cursor-pointer font-semibold whitespace-nowrap text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">JasperDiscovers</a> for Twitch streamers.
            Overlabels never sells your data or uses it to train AI. <a href="/ai" class="cursor-pointer text-sky-500 hover:underline" target="_blank" rel="noopener">Promised</a>.
          </p>
        </x-slot:footer>
      </x-brand-icons>
      </div>

      {{-- Two across at every width, so six products make three rows: the
           products are what sells, so they get the room. A card's small line
           is the chat command its product answers to; a product chat does not
           type into says what it does have instead. Hovering (or focusing) a
           card grows its caption box up over the image to show the product's
           description; it stays inside the card, so it never lands under the
           sticky nav. No hover on a phone, so no description there. --}}
      @php
          $cardLabels = [
              'chat-emote-bubbles' => '3 design presets',
              'twitch-chat-overlay' => '10+ design presets',
          ];
          // Hover copy, hand-kept: written to earn the click, so no setup
          // steps. A product missing here falls back to its catalogue description.
          $cardBlurbs = [
              'chat-checkin' => 'Viewers type !checkin and a city, and a pin with their name drops on a spinning globe. Watch your chat light up the map, and see who is watching from farthest away.',
              'chat-emote-bubbles' => 'Every emote your chat types floats across your stream in a bubble. Five PogChamps in chat, five PogChamps drifting up. Nothing to connect, no bot to add.',
              'chat-tower' => 'Your whole chat builds one tower together with !stack. It sways more with every block, and when it falls, everyone sees who toppled it. How high can your chat go?',
              'follower-bowling' => 'Your ten newest followers get racked up as pins. Chat lines up with !bowl and takes turns rolling at them. Can your chat bowl a strike?',
              'twitch-chat-overlay' => 'Your chat on stream, badges and emotes included, with nothing to connect and no bot to add. Pick one of ten looks, from a terminal to speech bubbles to a news ticker, and make it yours.',
              'twitch-chat-plinko' => 'Chat types !drop and a ball with their name bounces down a Plinko board on stream. Most land on 5. The edges pay 1000. Who is feeling lucky?',
          ];
          // These lead the grid in this order; everything else follows in
          // catalogue order. Hero cards only: the rows further down keep theirs.
          $cardsFirst = ['twitch-chat-plinko', 'twitch-chat-overlay'];
          $cards = collect($games)->sortBy(fn ($game) => array_search($game['slug'], $cardsFirst, true) === false
              ? count($cardsFirst)
              : array_search($game['slug'], $cardsFirst, true))->values();
          // A "New" badge, hand-kept. It stays until someone takes it off.
          $cardsNew = ['twitch-chat-plinko'];
      @endphp
      <ul class="mt-14 grid grid-cols-2 gap-3 sm:mt-20 sm:gap-6 lg:gap-8" aria-label="Products">
        @foreach ($cards as $game)
          @php $cmd = $command($game['description']); @endphp
          <li class="group min-w-0">
            <a href="#{{ $game['slug'] }}" aria-describedby="product-desc-{{ $game['slug'] }}" class="flex h-full cursor-pointer flex-col overflow-hidden border border-sidebar-border bg-card transition-[border-color,box-shadow] duration-200 group-hover:border-sky-500 group-hover:shadow-[0_0_0_1px_var(--color-sky-500)] focus-visible:border-sky-500 focus-visible:shadow-[0_0_0_1px_var(--color-sky-500)] focus-visible:outline-none">
              @if (!empty($game['hero']))
                <img src="{{ $game['hero'] }}" alt="" class="block aspect-video w-full border-b border-sidebar-border object-cover transition-colors duration-200 sm:group-focus-within:border-b-transparent sm:group-hover:border-b-transparent" width="1280" height="720" loading="lazy" />
              @endif
              <span class="relative flex flex-1 items-center justify-between gap-3 p-3 sm:px-5 sm:py-4">
                <span id="product-desc-{{ $game['slug'] }}" aria-hidden="true" class="absolute inset-x-0 bottom-full hidden grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-out group-focus-within:grid-rows-[1fr] group-hover:grid-rows-[1fr] sm:grid">
                  <span class="min-h-0 overflow-hidden">
                    <span class="block border-t-2 border-sky-500 bg-card px-5 pt-4 pb-1 text-[15px] leading-relaxed text-foreground">{{ $cardBlurbs[$game['slug']] ?? $game['description'] }}</span>
                  </span>
                </span>
                <span class="flex min-w-0 flex-col gap-1">
                  <span class="font-mono text-xs text-violet-600 sm:text-sm dark:text-violet-300">{{ $cardLabels[$game['slug']] ?? $cmd ?? 'no bot needed' }}</span>
                  <span class="text-sm font-semibold group-hover:text-sky-500 sm:text-xl">
                    {{ $game['name'] }}
                    @if (in_array($game['slug'], $cardsNew, true))
                      <span class="ml-1.5 inline-block border border-green-600 px-1.5 py-0.5 align-middle font-mono text-[10px] leading-none font-semibold tracking-wider text-green-600 sm:ml-3 sm:px-2 sm:py-1 sm:text-xs dark:border-green-400 dark:text-green-400">NEW</span>
                    @endif
                  </span>
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-y-0.5 group-hover:text-sky-500 sm:h-5 sm:w-5" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
              </span>
            </a>
          </li>
        @endforeach
      </ul>

    </div>
  </div>
</section>
