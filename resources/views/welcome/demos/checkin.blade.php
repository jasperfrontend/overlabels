{{--
  Chat Checkin, as it looks in OBS with the HUD switched on - and for real:
  resources/js/welcome/checkinDemo.ts mounts the product's own three.js
  globe into the scene, replays four viewers checking in, and opens the chat
  box under the scene so the visitor can type !checkin and a city and watch
  their own pin land. Places resolve through the same gazetteer the bot uses.

  What is rendered here is the fallback: the finished scene as plain text
  and a CSS disc where the globe goes. It is what shows before the globe
  chunk arrives, and what stays when it cannot (no WebGL, import failed).
  The chat box is hidden until the globe is live. The class names below are
  the ones checkinDemo.ts builds, so the two states look the same.
--}}
@php
    // Home is the Overlabels mark at Avarua, CK - see checkinDemoState.ts.
    $home = ['lat' => -21.2075, 'lng' => -159.77546];
    $checkins = [
        ['name' => 'pixelmoth', 'color' => '#1E90FF', 'query' => 'Lisbon', 'label' => 'Lisbon, PT', 'lat' => 38.7167, 'lng' => -9.1333],
        ['name' => 'quietfox', 'color' => '#8A2BE2', 'query' => 'Austin', 'label' => 'Austin, US', 'lat' => 30.2672, 'lng' => -97.7431],
        ['name' => 'kettle_', 'color' => '#FF69B4', 'query' => 'Cape Town', 'label' => 'Cape Town, ZA', 'lat' => -33.9258, 'lng' => 18.4232],
        ['name' => 'sunroof', 'color' => '#2E8B57', 'query' => 'Osaka', 'label' => 'Osaka, JP', 'lat' => 34.6937, 'lng' => 135.5023],
    ];
    $km = function (array $a, array $b): float {
        $rad = fn (float $d) => $d * M_PI / 180;
        $h = sin($rad($b['lat'] - $a['lat']) / 2) ** 2 + cos($rad($a['lat'])) * cos($rad($b['lat'])) * sin($rad($b['lng'] - $a['lng']) / 2) ** 2;

        return 2 * 6371 * asin(sqrt($h));
    };
    // Numbers as the product shows them: the control holds km to one decimal,
    // |distance:km and |distance:mi render up to two, no trailing zeros; the
    // bot speaks the one-decimal km with a trailing .0 dropped.
    $trim = fn (string $n): string => rtrim(rtrim($n, '0'), '.');
    $farthest = collect($checkins)->sortByDesc(fn ($c) => $km($home, $c))->first();
    $farKm = round($km($home, $farthest), 1);
    $farMi = round($farKm / 1.609344, 2);
    $last = end($checkins);
@endphp
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border" data-checkin-demo>
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Chat Checkin, live in OBS</span>
  </div>
  <div class="ol-demo ol-ck" aria-label="Chat Checkin demo: viewers type !checkin with their city and a pin lands on a spinning globe">
    <div class="ol-ck__left">
      <div class="ol-ck__chat" data-checkin-chat aria-live="polite">
        @foreach ($checkins as $line)
          @php $away = round($km($home, $line), 1); @endphp
          <div class="ol-ck__line">
            <span class="ol-ck__name" style="color: {{ $line['color'] }}">{{ $line['name'] }}</span>:
            <span class="ol-ck__cmd">!checkin {{ $line['query'] }}</span>
          </div>
          <div class="ol-ck__line ol-ck__bot">
            <span class="ol-ck__name">overlabels</span>: {{ $line['name'] }} checked in from {{ $line['label'] }}!@if ($away >= 1) That is {{ $trim(number_format($away, 1)) }} km away.@endif
          </div>
        @endforeach
      </div>
      <div class="ol-ck__hud" data-checkin-hud aria-hidden="true">
        <div data-hud="count">{{ count($checkins) }} checkins from {{ count($checkins) }} countries</div>
        <div data-hud="latest">{{ $last['name'] }} checked in last from {{ $last['label'] }}</div>
        <div data-hud="farthest">farthest from home: {{ $trim(number_format($farKm, 2)) }}km ({{ $trim(number_format($farMi, 2)) }}mi) by {{ $farthest['name'] }}</div>
      </div>
    </div>
    {{-- The renderer forces position: relative on the element it mounts into, so the slot does the placing. --}}
    <div class="ol-ck__globe-slot" aria-hidden="true">
      <div class="ol-ck__globe" data-checkin-globe>
        <div class="ol-ck__disc" data-checkin-fallback></div>
      </div>
    </div>
  </div>
  <form class="ol-ck__prompt" data-checkin-form hidden>
    <label for="checkin-demo-input-{{ $checkinId ?? 'row' }}" class="ol-ck__prompt-hint">You are in chat now. Type !checkin and a city.</label>
    <div class="ol-ck__prompt-row">
      <input
        id="checkin-demo-input-{{ $checkinId ?? 'row' }}"
        class="ol-ck__input"
        data-checkin-input
        type="text"
        placeholder="!checkin Avarua"
        maxlength="120"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        enterkeyhint="send"
      />
      <button type="submit" class="btn btn-primary cursor-pointer" data-checkin-send>Send</button>
    </div>
  </form>
</div>
