{{--
  A chat game's live demo: the product's REAL overlay (ProductDemo, the
  recipe built with sample values) in a frame, with the designer's scenes
  played into it on a loop by resources/js/welcome/overlayDemo.ts. What you
  see is the product, not a drawing of it.

  Until it starts, the product's hero artwork stands in. On the homepage a
  click starts it, so a page of demos loads none of them up front; a product
  page passes `autostart` and it starts once it scrolls into view.

  $product: the product slug. $label: the title bar text.
--}}
@php
    $overlayDemo = app(\App\Services\Recipes\ProductDemo::class)->frame($product);
    $overlayHero = app(\App\Services\Recipes\RecipeCatalog::class)->find($product)['hero'] ?? null;
@endphp
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border">
  {{-- The product page passes bare: it has its own heading, and a pretend OBS title bar there reads as filler. --}}
  @unless ($bare ?? false)
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">{{ $label }}, live in OBS</span>
  </div>
  @endunless
  <div
    class="relative aspect-video w-full overflow-hidden bg-[#0e0b16]"
    @if ($overlayDemo)
      data-overlay-demo="{{ json_encode($overlayDemo) }}"
      data-overlay-demo-src="{{ route('products.demo-frame', $product) }}"
      data-overlay-demo-autostart="{{ ($autostart ?? false) ? '1' : '0' }}"
    @endif
  >
    @if ($overlayHero)
      <img
        src="{{ $overlayHero }}"
        alt="{{ $label }} on stream"
        class="absolute inset-0 h-full w-full object-cover"
        width="1280"
        height="720"
        loading="lazy"
        data-overlay-demo-poster
      />
    @endif
    @if ($overlayDemo)
      <button
        type="button"
        class="group absolute inset-0 flex cursor-pointer items-center justify-center bg-black/45 transition-colors hover:bg-black/30"
        data-overlay-demo-start
        @if ($autostart ?? false) hidden @endif
      >
        <span class="inline-flex items-center gap-3 border border-white/30 bg-black/60 px-5 py-3 font-medium text-white transition-transform group-hover:scale-105">
          <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
          Play the live demo
        </span>
      </button>
    @endif
  </div>
</div>
