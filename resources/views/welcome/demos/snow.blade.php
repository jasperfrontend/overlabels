{{--
  Snow Overlay for OBS, as it looks in OBS: a 60 fps recording of the real
  overlay, looped. Snow has no chat and no scenes to play, so there is
  nothing for the live overlay demo (_overlay-demo) to act out, and a video
  is what it actually looks like on stream.

  Same arrangement as the live demos: the hero stands in as the poster, the
  video loads nothing up front (preload="none"), a click starts it on the
  homepage, and a product page passes `autostart` so it starts once it
  scrolls into view (resources/js/welcome/videoDemo.ts).
--}}
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border">
  @unless ($bare ?? false)
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Snow Overlay for OBS, live in OBS</span>
  </div>
  @endunless
  <div class="relative aspect-video w-full overflow-hidden bg-[#0b1626]" data-video-demo data-video-demo-autostart="{{ ($autostart ?? false) ? '1' : '0' }}">
    <video
      class="absolute inset-0 h-full w-full object-cover"
      src="/products/snow-overlay/demo.mp4"
      poster="/products/snow-overlay-hero.jpg"
      width="1280"
      height="720"
      preload="none"
      muted
      loop
      playsinline
      aria-label="Snow falling over a stream, recorded from the overlay in OBS"
    ></video>
    <button
      type="button"
      class="group absolute inset-0 flex cursor-pointer items-center justify-center bg-black/45 transition-colors hover:bg-black/30"
      data-video-demo-start
      @if ($autostart ?? false) hidden @endif
    >
      <span class="inline-flex items-center gap-3 border border-white/30 bg-black/60 px-5 py-3 font-medium text-white transition-transform group-hover:scale-105">
        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
        Play the demo
      </span>
    </button>
  </div>
</div>
