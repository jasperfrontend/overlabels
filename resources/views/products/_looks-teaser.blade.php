{{--
  The three-knob designer: Start from, Accent, Font size, beside a preview
  that runs the overlay's own stylesheet in a sandboxed frame with made-up
  chat. welcome/looksTeaser.ts plays it by itself (a cursor picks a look, an
  accent, a size) until the visitor touches it, then hands it over. The rest
  of the designer is named under it, not shown. Data from
  ProductController::looksTeaser().
--}}
@php
    $first = $teaser['presets'][0];
    $srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
        .'<link rel="stylesheet" href="'.e($teaser['fonts_url']).'">'
        .'<style>'.$teaser['css'].'</style></head><body>'
        .'<div class="ol-chat skin-'.e($first['values'][$teaser['skin_key']] ?? 'clean').' layout-'.e($first['values']['layout'] ?? 'bottom').' bg-'.e($first['values']['background'] ?? 'solid').'"></div>'
        .'</body></html>';
    $accents = ['#9146ff', '#3dff3d', '#ff4f9a', '#ffb020', '#22d3ee', '#f43f5e'];
    $rest = ['Layout', 'How long a message stays', 'Font', 'Emote size', 'Names in their Twitch colours', 'Background', 'Badges', 'Hiding commands and bots', 'Your own saved looks'];
@endphp
<div data-looks-teaser class="relative">
  <script type="application/json" data-looks-teaser-data>@json(['skinKey' => $teaser['skin_key'], 'presets' => $teaser['presets']])</script>

  <div class="grid gap-8 lg:grid-cols-[20rem_minmax(0,1fr)] lg:gap-10">
    <div class="flex flex-col gap-7">
      {{-- Your name on the broadcaster's line: a logged-in streamer starts with
           their own, anyone else types it. It only ever goes into the preview. --}}
      <div>
        <label for="looks-teaser-name" class="mb-2.5 block text-sm font-bold">Your name in chat</label>
        <input id="looks-teaser-name" data-name type="text" maxlength="25" autocomplete="off" spellcheck="false" placeholder="jasperdiscovers"
               value="{{ auth()->user()?->twitch_data['display_name'] ?? auth()->user()?->name ?? '' }}"
               class="w-full rounded-md bg-foreground/5 px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-violet-500" />
      </div>

      <div>
        <p class="mb-2.5 text-sm font-bold">Start from</p>
        <div class="flex flex-wrap gap-1.5">
          @foreach ($teaser['presets'] as $preset)
            <button type="button" data-look="{{ $preset['key'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="cursor-pointer rounded-full bg-foreground/5 px-3 py-1.5 text-sm transition-colors hover:bg-foreground/10 aria-pressed:bg-violet-500 aria-pressed:text-white">{{ $preset['label'] }}</button>
          @endforeach
        </div>
      </div>

      <div>
        <p class="mb-2.5 text-sm font-bold">Accent</p>
        <div class="flex flex-wrap gap-2">
          @foreach ($accents as $accent)
            <button type="button" data-accent="{{ $accent }}" aria-label="Accent {{ $accent }}" aria-pressed="false" class="size-8 cursor-pointer rounded-full ring-offset-2 ring-offset-background transition-shadow aria-pressed:ring-2 aria-pressed:ring-foreground" style="background-color: {{ $accent }}"></button>
          @endforeach
        </div>
      </div>

      <div>
        <label for="looks-teaser-size" class="mb-2.5 flex justify-between text-sm font-bold">Font size <output data-size-out class="font-mono font-normal text-muted-foreground">{{ $first['values']['font_size'] ?? 22 }}</output></label>
        <input id="looks-teaser-size" data-size type="range" min="14" max="34" step="1" value="{{ $first['values']['font_size'] ?? 22 }}" class="w-full cursor-pointer accent-violet-500" />
      </div>

      {{-- The way into the real thing. The designer only opens on an
           install, so everyone else goes to the installer, whose first
           button after the one-click install is the designer. --}}
      <div class="flex flex-col items-start gap-3">
        <p class="text-sm text-muted-foreground">
          <span class="font-semibold text-foreground">That's 3 of the designer's 13 controls.</span>
          It also has: {{ implode(' · ', $rest) }}.
        </p>
        <a href="{{ $installed ? route('products.design', $product['slug']) : route('products.manage', $product['slug']) }}" class="btn btn-primary cursor-pointer">
          Open the full designer
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
        @unless ($installed)
          <p class="text-xs text-muted-foreground">Free. One click installs it, and the designer opens from the same page.</p>
        @endunless
      </div>
    </div>

    <div class="order-first min-w-0 lg:order-none">
      {{-- The checkerboard is this page, as in the designer: the overlay itself is transparent. --}}
      <div class="relative h-[26rem] overflow-hidden rounded-md sm:h-[30rem]" style="background-color: #17131f; background-image: conic-gradient(rgb(255 255 255 / 0.04) 25%, transparent 0 50%, rgb(255 255 255 / 0.04) 0 75%, transparent 0); background-size: 28px 28px">
        <iframe data-looks-frame title="Twitch Chat Overlay preview with made-up chat" srcdoc="{{ $srcdoc }}" sandbox="allow-same-origin" class="absolute inset-x-0 bottom-0 h-full w-full border-0 transition-[height] duration-300" style="color-scheme: normal" loading="lazy"></iframe>
      </div>
      <p data-look-blurb class="mt-4 min-h-[3em] text-[15px] text-muted-foreground"><span class="font-semibold text-foreground">{{ $first['label'] }}.</span> {{ $first['blurb'] }}</p>
    </div>
  </div>

  {{-- The cursor that plays the knobs until the visitor takes over. --}}
  <svg data-teaser-cursor aria-hidden="true" viewBox="0 0 24 24" class="pointer-events-none absolute top-0 left-0 z-10 hidden size-6 drop-shadow-[0_2px_3px_rgb(0_0_0_/_0.5)] transition-transform duration-700 ease-in-out"><path d="M4 2l16 9.5-7 1.5-3.5 6.5z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>
</div>
