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
          Give your <span class="whitespace-nowrap">Twitch chat</span><br />something to play.
        </h1>

        <p class="mb-4 max-w-2xl text-xl leading-relaxed text-foreground">
          Looking for Twitch chat games your viewers have never played? You found them. Overlabels makes small
          games your Twitch chat plays live on your stream, and nobody else has them.
        </p>
        <p class="mb-10 max-w-2xl text-base text-foreground">
          Free. One click to install. One link to paste into OBS. Works with the Twitch account you already have.
        </p>

        <div class="flex flex-wrap items-center gap-4">
          <a href="#products" class="btn btn-primary cursor-pointer">
            See the products
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
          </a>
          @auth
            <a href="{{ route('dashboard.index') }}" class="btn btn-secondary cursor-pointer">Go to dashboard</a>
          @else
            <a href="/login" class="btn btn-secondary gap-2 cursor-pointer">
              <svg viewBox="0 0 24 24" fill="currentColor" class="size-4"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0 1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" /></svg>
              Log in with Twitch
            </a>
          @endauth
        </div>
      </div>

      {{-- Where it runs, and who made it. Three short lines under the buttons on
           a phone; on desktop it fills the hero's right half. The Twitch,
           Streamlabs and OBS glyphs are the Simple Icons (CC0) ones
           products/_works-with.blade.php carries. StreamElements is not on Simple
           Icons; its full-colour mark is a local copy in public/brands (never
           hotlinked). It works because an Overlabels overlay is a plain browser
           source: nothing in it talks to StreamElements. --}}
      @php
          $worksWith = [
              ['name' => 'Twitch', 'tile' => 'bg-[#9146ff] text-white', 'svg' => 'size-5 lg:size-8', 'd' => 'M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z'],
              ['name' => 'OBS Studio', 'tile' => 'rounded-full bg-[#1b1a1f] text-white ring-1 ring-white/15', 'svg' => 'size-8 lg:size-12 xl:size-14', 'd' => 'M12 24C5.383 24 0 18.617 0 12S5.383 0 12 0s12 5.383 12 12s-5.383 12-12 12m0-22.891C5.995 1.109 1.11 5.995 1.11 12S5.995 22.89 12 22.89S22.89 18.005 22.89 12S18.005 1.109 12 1.109M6.182 5.99c.352-1.698 1.503-3.229 3.05-3.996c-.269.273-.595.483-.844.78c-1.02 1.1-1.48 2.692-1.199 4.156c.355 2.235 2.455 4.06 4.732 4.028c1.765.079 3.485-.937 4.348-2.468c1.848.063 3.645 1.017 4.7 2.548c.54.799.962 1.736.991 2.711c-.342-1.295-1.202-2.446-2.375-3.095a4.9 4.9 0 0 0-3.772-.425c-1.56.448-2.849 1.723-3.293 3.293c-.377 1.25-.216 2.628.377 3.772c-.825 1.429-2.315 2.449-3.932 2.756c-1.244.261-2.551.059-3.709-.464c1.036.302 2.161.355 3.191-.011a4.91 4.91 0 0 0 3.024-2.935c.556-1.49.345-3.261-.591-4.54c-.7-1.007-1.803-1.717-3.002-1.969c-.38-.068-.764-.098-1.148-.134c-.611-1.231-.834-2.66-.528-3.996z'],
              ['name' => 'Streamlabs OBS', 'tile' => 'bg-[#80f5d2] text-[#09161d]', 'svg' => 'size-5 lg:size-8', 'd' => 'M8.688 1.346a1.4 1.4 0 0 0-.274.006a8.4 8.4 0 0 0-1.484.308A10.06 10.06 0 0 0 .32 8.27a8.5 8.5 0 0 0-.31 1.486a1.343 1.343 0 0 0 2.662.332a5.7 5.7 0 0 1 .21-1.02a7.37 7.37 0 0 1 4.845-4.847c.288-.09.615-.157 1.02-.207A1.34 1.34 0 0 0 9.91 2.516a1.34 1.34 0 0 0-1.223-1.17Zm4.049 5.223c-2.63 0-3.944 0-4.948.511a4.7 4.7 0 0 0-2.048 2.051c-.512 1.004-.512 2.318-.512 4.947v4.29c0 1.501-.001 2.254.29 2.828c.258.504.669.914 1.173 1.171c.574.292 1.326.291 2.828.291h6.97c2.628 0 3.945.002 4.948-.51a4.7 4.7 0 0 0 2.05-2.05C24 19.094 24 17.78 24 15.15v-1.073c0-2.629 0-3.943-.512-4.947a4.7 4.7 0 0 0-2.05-2.05c-1.003-.512-2.32-.512-4.948-.512zm.537 6.705c.74 0 1.34.6 1.34 1.34v2.683a1.342 1.342 0 0 1-2.682 0v-2.684c0-.74.602-1.34 1.342-1.34m5.363 0c.74 0 1.34.6 1.34 1.34v2.683a1.34 1.34 0 0 1-2.68 0v-2.684c0-.74.599-1.34 1.34-1.34'],
              ['name' => 'StreamElements', 'tile' => 'bg-[#1b1a1f] ring-1 ring-white/15', 'img' => '/brands/streamelements.svg'],
          ];
      @endphp
      <div class="flex flex-col items-start gap-4 lg:items-center lg:gap-10 lg:text-center">
        <p class="text-sm font-semibold text-foreground lg:text-lg">Every Overlabels product works with</p>
        <ul class="flex flex-wrap gap-3 lg:gap-4 xl:gap-6" aria-label="Works with">
          @foreach ($worksWith as $app)
            <li>
              <span tabindex="0" role="img" aria-label="{{ $app['name'] }}" class="group relative flex size-10 cursor-default items-center justify-center rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-sky-500 lg:size-16 lg:rounded-2xl xl:size-20 {{ $app['tile'] }}">
                @isset($app['d'])
                  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="{{ $app['svg'] }}"><path d="{{ $app['d'] }}"/></svg>
                @else
                  <img src="{{ $app['img'] }}" alt="" class="size-7 lg:size-11 xl:size-14" width="80" height="80" />
                @endisset
                <span aria-hidden="true" class="pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 translate-y-1 rounded-sm bg-foreground px-2 py-1 text-xs font-semibold whitespace-nowrap text-background opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100">{{ $app['name'] }}</span>
              </span>
            </li>
          @endforeach
        </ul>
        <p class="text-sm text-foreground lg:max-w-sm lg:text-base">
          Always 100% free and <a href="https://github.com/jasperfrontend/overlabels" class="cursor-pointer text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">open source</a>.
          Created by <a href="https://www.twitch.tv/jasperdiscovers" class="cursor-pointer font-semibold text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">/JasperDiscovers</a> for Twitch streamers.
        </p>
      </div>
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
