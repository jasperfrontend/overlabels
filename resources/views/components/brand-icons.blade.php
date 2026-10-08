{{--
  A row of brand tiles with an optional title above or beside it and an
  optional footer under it: "Every Overlabels product works with" on the
  homepage, "Works with" / "Shows emotes from" on a product page. Every tile
  is the same square with a solid background carrying the brand, the glyph
  centred on it, and the brand's name in a tooltip on hover or keyboard focus.
  The marks and their colours live in App\Support\BrandMarks.

  Props:
    icons    keys from BrandMarks::MARKS, or ['icon' => key, 'bg' => '#hex'] to recolour a tile
    title    optional; the line naming what the icons are
    layout   stacked (title above the icons) | inline (title left of them)
    align    left | center | right
    alignLg  optional, the same from lg up (the homepage is left on a phone, centred on desktop)
    size     sm (product pages) | lg (the homepage hero)
  Slot: footer, optional, under the icons.

  Usage:
    <x-brand-icons title="Works with" :icons="['twitch', 'obs', 'streamlabs']" />
--}}
@props([
    'icons' => [],
    'title' => null,
    'layout' => 'stacked',
    'align' => 'left',
    'alignLg' => null,
    'size' => 'sm',
])
@php
    // Literal class strings, so Tailwind's scanner sees every one of them.
    $items = ['left' => 'items-start text-left', 'center' => 'items-center text-center', 'right' => 'items-end text-right'];
    $itemsLg = ['left' => 'lg:items-start lg:text-left', 'center' => 'lg:items-center lg:text-center', 'right' => 'lg:items-end lg:text-right'];
    $justify = ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end'];
    $justifyLg = ['left' => 'lg:justify-start', 'center' => 'lg:justify-center', 'right' => 'lg:justify-end'];

    $lg = $size === 'lg';
    $stacked = $layout === 'stacked';

    $wrap = $stacked
        ? 'flex flex-col '.($lg ? 'gap-4 lg:gap-10' : 'gap-3').' '.$items[$align].' '.($alignLg ? $itemsLg[$alignLg] : '')
        : 'flex flex-wrap items-center gap-x-4 gap-y-3 '.$justify[$align].' '.($alignLg ? $justifyLg[$alignLg] : '');
    $row = 'flex flex-wrap '.($lg ? 'gap-3 lg:gap-4 xl:gap-6' : 'gap-2.5').' '.$justify[$align].' '.($alignLg ? $justifyLg[$alignLg] : '');
    $tile = $lg ? 'size-10 rounded-lg lg:size-16 lg:rounded-2xl xl:size-20' : 'size-11 rounded-xl';
    $titleClass = $lg ? 'text-sm font-semibold lg:text-lg' : 'text-sm font-bold';
@endphp
<div {{ $attributes->class([$wrap]) }}>
  @if ($title)
    <p class="{{ $titleClass }} text-foreground">{{ $title }}</p>
  @endif
  <ul class="{{ $row }}" aria-label="{{ $title ?? 'Works with' }}">
    @foreach ($icons as $icon)
      @php $mark = \App\Support\BrandMarks::resolve($icon); @endphp
      <li>
        <span tabindex="0" role="img" aria-label="{{ $mark['name'] }}" class="group relative flex cursor-default items-center justify-center ring-1 ring-white/10 ring-inset outline-none focus-visible:ring-2 focus-visible:ring-sky-500 {{ $tile }}" style="background-color: {{ $mark['bg'] }}">
          @isset($mark['img'])
            <img src="{{ $mark['img'] }}" alt="" @class(['block', 'size-full rounded-[inherit] object-cover' => $mark['scale'] === 100, 'object-contain' => $mark['scale'] !== 100]) @if ($mark['scale'] !== 100) style="width: {{ $mark['scale'] }}%; height: {{ $mark['scale'] }}%" @endif />
          @else
            <svg viewBox="{{ $mark['viewBox'] }}" fill="{{ $mark['fg'] }}" aria-hidden="true" style="width: {{ $mark['scale'] }}%; height: {{ $mark['scale'] }}%">
              @foreach ($mark['paths'] as $d)
                <path d="{{ $d }}" />
              @endforeach
            </svg>
          @endisset
          <span aria-hidden="true" class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 translate-y-1 rounded-sm bg-foreground px-2 py-1 text-xs font-semibold whitespace-nowrap text-background opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100">{{ $mark['name'] }}</span>
        </span>
      </li>
    @endforeach
  </ul>
  @isset($footer)
    {{ $footer }}
  @endisset
</div>
