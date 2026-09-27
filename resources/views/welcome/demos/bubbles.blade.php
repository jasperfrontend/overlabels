{{--
  Chat Emote Bubbles, as it looks in OBS: whatever chat types floats up the
  screen in a bubble, continuously, at random positions/speeds/drift rather
  than a scripted one-shot narrative like the other demos - that's the actual
  product, chat can post any time and a bubble rises whenever it does. The
  bubble carries the real emote IMAGE (never a text label, matching the real
  overlay), and every bubble uses the SAME plain glass look: the product
  picks one skin per install, never a random mix of tints.

  Randomised in PHP on every request, not fixed nth-child rules: a fixed
  left-to-right position paired with a strictly increasing delay is a single
  diagonal wave that never varies. Pure CSS otherwise
  (resources/css/welcome-demos.css).
--}}
@php
    // Real global Twitch emote ids, hand-verified against twitchemotes.com
    // and Twitch's own CDN (curl 200 on every one) rather than guessed - a
    // wrong id here is a broken image in a marketing demo, not a test
    // failure. High-res global emotes on purpose, several of them channel
    // subscriber emotes promoted to the global set: those ship at a much
    // higher native resolution than the original ASCII-name (Kappa-era) v1
    // emotes, and read far better blown up in a bubble. A hash-style id
    // (emotesv2_...) is just as valid in the v2 CDN path as a numeric one.
    $emotes = [
        'Poooound' => '117484',
        'GoldPLZ' => 'emotesv2_c1f4899e65cf4f53b2fd98e15733973a',
        'PartyHat' => '965738',
        'bleedPurple' => '62835',
        'SingsNote' => '300116350',
        'Kappa' => '25',
        'Jebasted' => 'emotesv2_031bf329c21040a897d55ef471da3dd3',
        'TwitchUnity' => '196892',
    ];

    $bubbleChat = [
        ['name' => 'pixelmoth', 'color' => '#1E90FF', 'emote' => 'Kappa'],
        ['name' => 'tea_and_raids', 'color' => '#FF7F50', 'emote' => 'GoldPLZ GoldPLZ'],
        ['name' => 'noodlebyte', 'color' => '#9ACD32', 'emote' => 'TwitchUnity'],
    ];

    $names = array_keys($emotes);
    $bubbles = collect(range(1, 14))->map(function () use ($emotes, $names) {
        $name = $names[array_rand($names)];

        return [
            'name' => $name,
            'url' => "https://static-cdn.jtvnw.net/emoticons/v2/{$emotes[$name]}/default/dark/2.0",
            // Independent random draws, not a shared index: a bubble's
            // position tells you nothing about its delay, duration or which
            // way it drifts. That decorrelation is what stops the field
            // reading as one wave.
            'x' => random_int(2, 94),
            'size' => random_int(50, 90) / 10,
            'delay' => random_int(0, 6800) / 1000,
            'dur' => random_int(48, 82) / 10,
            'drift' => random_int(-48, 48) / 10,
            'rot' => random_int(-18, 18),
        ];
    });
@endphp
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border">
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Chat Emote Bubbles, live in OBS</span>
  </div>
  <div class="ol-demo ol-bub" aria-label="Chat Emote Bubbles demo: every emote a viewer types floats up the screen in a bubble">
    <div class="ol-bub__chat" aria-hidden="true">
      @foreach ($bubbleChat as $line)
        <div class="ol-demo__line" style="opacity: 1; transform: none;">
          <span class="ol-demo__name" style="color: {{ $line['color'] }}">{{ $line['name'] }}</span>: {{ $line['emote'] }}
        </div>
      @endforeach
    </div>
    <div class="ol-bub__stage" aria-hidden="true">
      @foreach ($bubbles as $b)
        <div
          class="ol-bub__bubble"
          style="--x: {{ $b['x'] }}%; --size: {{ $b['size'] }}; --delay: {{ $b['delay'] }}s; --dur: {{ $b['dur'] }}s; --driftn: {{ $b['drift'] }}; --rotn: {{ $b['rot'] }};"
        >
          <img src="{{ $b['url'] }}" alt="{{ $b['name'] }}" />
        </div>
      @endforeach
    </div>
  </div>
</div>
