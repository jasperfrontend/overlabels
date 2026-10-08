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
          Free Twitch chat games your viewers play by typing a !command in chat, plus a <a href="#twitch-chat-overlay" class="cursor-pointer text-sky-500 hover:underline">Twitch Chat Overlay</a> you can
          style any way you like in just a few clicks. Put a quick <a href="#chat-tower" class="cursor-pointer text-sky-500 hover:underline">Chat Tower</a> or <a href="#follower-bowling" class="cursor-pointer text-sky-500 hover:underline">Follower Bowling</a> game up while you grab a drink,
          and chat has something to do until you're back.
        </p>

        <div class="flex flex-wrap items-center gap-4">
          <a href="#products" class="btn btn-primary cursor-pointer">
            See the products
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
          </a>
          @guest
            <a href="/login" class="btn btn-secondary gap-2 cursor-pointer">
              <svg viewBox="0 0 24 24" fill="currentColor" class="size-4"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0 1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" /></svg>
              Log in with Twitch
            </a>
          @endguest
          <p class="flex max-w-md items-start gap-2.5 text-sm text-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-5 shrink-0 text-green-500" aria-hidden="true"><rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
            <span>Free forever. No registration required. Overlabels doesn't steal your data, nor is any of your data used to train AI. <a href="/ai" class="cursor-pointer text-sky-500 hover:underline">Promised</a>.</span>
          </p>
        </div>
      </div>

      {{-- Where it runs, and who made it. Three short lines under the buttons on
           a phone; on desktop it fills the hero's right half. StreamElements
           works because an Overlabels overlay is a plain browser source:
           nothing in it talks to StreamElements. --}}
      <x-brand-icons title="Every Overlabels product works with" :icons="['twitch', 'obs', 'streamlabs', 'streamelements']" size="lg" align="left" align-lg="center">
        <x-slot:footer>
          <p class="text-sm text-foreground lg:max-w-sm lg:text-base">
            Always 100% free and <a href="https://github.com/jasperfrontend/overlabels" class="cursor-pointer text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">open source</a>.
            Created by <a href="https://www.twitch.tv/jasperdiscovers" class="cursor-pointer font-semibold whitespace-nowrap text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">/JasperDiscovers</a> for Twitch streamers.
          </p>
        </x-slot:footer>
      </x-brand-icons>
      </div>

      {{-- Two across on a phone, then as many as fit at 11rem or wider:
           five fills one row on desktop, and a sixth still fits it.
           A card's small line is the chat command its product answers to; a
           product chat does not type into says what it does have instead. --}}
      @php
          $cardLabels = [
              'chat-emote-bubbles' => '3 design presets',
              'twitch-chat-overlay' => '10+ design presets',
          ];
      @endphp
      <ul class="mt-14 grid grid-cols-2 gap-3 sm:mt-20 sm:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] sm:gap-4" aria-label="Products">
        @foreach ($games as $game)
          @php $cmd = $command($game['description']); @endphp
          <li class="min-w-0">
            <a href="#{{ $game['slug'] }}" class="group flex h-full cursor-pointer flex-col overflow-hidden rounded-sm border border-sidebar-border bg-card transition-colors hover:border-sky-500 focus-visible:border-sky-500 focus-visible:outline-none">
              @if (!empty($game['hero']))
                <img src="{{ $game['hero'] }}" alt="" class="block aspect-video w-full border-b border-sidebar-border object-cover transition-transform duration-300 group-hover:scale-[1.03]" width="1280" height="720" loading="lazy" />
              @endif
              <span class="flex flex-1 flex-col gap-1 p-3">
                <span class="font-mono text-xs text-violet-600 dark:text-violet-300">{{ $cardLabels[$game['slug']] ?? $cmd ?? 'no bot needed' }}</span>
                <span class="flex items-center justify-between gap-2 text-sm font-semibold group-hover:text-sky-500">
                  {{ $game['name'] }}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-y-0.5 group-hover:text-sky-500" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
                </span>
              </span>
            </a>
          </li>
        @endforeach
      </ul>

    </div>
  </div>
</section>
