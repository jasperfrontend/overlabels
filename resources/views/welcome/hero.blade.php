{{--
  The hero sells the products, not the syntax. A streamer arriving here should
  read one sentence and know what this is for; the code lives further down,
  under the "Build your own" fold. The right column is a tabbed showcase of
  the four product demos - demos of products, not decoration.
--}}
<section class="border-b border-sidebar-accent py-20 sm:py-28">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto grid max-w-5xl items-center gap-12 lg:grid-cols-[1.05fr_1fr] lg:gap-16 [&>*]:min-w-0">

      <div>
        <h1 class="mb-6 text-4xl leading-[1.05] font-bold tracking-tight sm:text-5xl lg:text-6xl">
          Give your chat<br />something to play.
        </h1>

        <p class="mb-4 max-w-xl text-xl leading-relaxed text-foreground">
          Chat builds a tower. Chat bowls your newest followers. Chat pins itself on a spinning globe.
          Small games that run on your stream, built by Overlabels, and nobody else has them.
        </p>
        <p class="mb-10 max-w-xl text-base text-foreground">
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
              <svg viewBox="0 0 24 24" fill="currentColor" class="size-4 text-[#9146FF]"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0 1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" /></svg>
              Log in with Twitch
            </a>
          @endauth
        </div>
      </div>

      {{-- The showcase: one tab per product, named by what chat types. It
           auto-advances at the end of each loop until the visitor picks a
           tab (wireShowcase in welcome/app.ts). The same demo partials the
           shelf uses; the rows below are the full-width version with the
           description, this is the teaser. --}}
      @php
          $showcase = [
              ['key' => 'tower', 'cmd' => '!stack', 'name' => 'Chat Tower', 'seconds' => 16],
              ['key' => 'bowling', 'cmd' => '!bowl', 'name' => 'Follower Bowling', 'seconds' => 10],
              ['key' => 'checkin', 'cmd' => '!checkin', 'name' => 'Chat Checkin', 'seconds' => 20],
              ['key' => 'chat', 'cmd' => 'chat', 'name' => 'Twitch Chat Overlay', 'seconds' => 18],
          ];
      @endphp
      <div data-showcase data-tabs="showcase">
        <div class="mb-3 flex flex-wrap gap-0 border-b border-sidebar-border" role="tablist" aria-label="Products">
          @foreach ($showcase as $tab)
            <button
              type="button"
              role="tab"
              data-tab="{{ $tab['key'] }}"
              aria-controls="showcase-{{ $tab['key'] }}"
              class="-mb-px flex shrink-0 cursor-pointer flex-col items-start gap-0.5 border-b-2 px-3 py-2 text-left text-sm transition-colors {{ $loop->first ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground' }}"
            >
              <span class="font-mono text-xs">{{ $tab['cmd'] }}</span>
              <span class="text-xs">{{ $tab['name'] }}</span>
            </button>
          @endforeach
        </div>
        @foreach ($showcase as $tab)
          <div id="showcase-{{ $tab['key'] }}" role="tabpanel" data-tab-panel="{{ $tab['key'] }}" data-duration="{{ $tab['seconds'] }}" @class(['hidden' => ! $loop->first])>
            @include('welcome.demos.'.$tab['key'], ['checkinId' => 'hero'])
          </div>
        @endforeach
      </div>

    </div>
  </div>
</section>
