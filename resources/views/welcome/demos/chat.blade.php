{{--
  Twitch Chat Overlay, as it looks in OBS, cycling three of its ten looks:
  Terminal, Bubbles, Broadcast. Names in their Twitch colours, badges, an
  emote, the first-message chip. Pure CSS (resources/css/welcome-demos.css),
  18 second loop, six seconds per look.
--}}
@php
    $chatLooks = [
        ['key' => 'terminal', 'label' => 'Look 1 of 10: Terminal'],
        ['key' => 'bubbles', 'label' => 'Look 2 of 10: Bubbles'],
        ['key' => 'broadcast', 'label' => 'Look 3 of 10: Broadcast'],
    ];
    $chatMessages = [
        ['badge' => 'mod', 'name' => 'rivermoss', 'color' => '#FF7F50', 'text' => 'that jump was clean', 'emote' => '😂', 'first' => false],
        ['badge' => 'sub', 'name' => 'pixel_kat', 'color' => '#1E90FF', 'text' => 'hello from the night shift', 'emote' => '👋', 'first' => false],
        ['badge' => null, 'name' => 'lena_streams', 'color' => '#9ACD32', 'text' => 'found you through a raid, staying', 'emote' => null, 'first' => true],
        ['badge' => 'vip', 'name' => 'quietfox', 'color' => '#8A2BE2', 'text' => 'chat looks so good on stream', 'emote' => '🔥', 'first' => false],
    ];
@endphp
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border">
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Twitch Chat Overlay, live in OBS</span>
  </div>
  <div class="ol-demo ol-demo--chat" aria-label="Twitch Chat Overlay demo: chat messages with badges and emotes, shown in three of the ten looks">
    <div class="ol-demo__label" aria-hidden="true">
      @foreach ($chatLooks as $look)
        <span>{{ $look['label'] }}</span>
      @endforeach
    </div>
    @foreach ($chatLooks as $look)
      <div class="ol-tc__look ol-tc__look--{{ $look['key'] }}" aria-hidden="true">
        @foreach ($chatMessages as $msg)
          <div class="ol-tc__msg">
            @if ($msg['badge'])
              <span class="ol-tc__badge ol-tc__badge--{{ $msg['badge'] }}"></span>
            @endif
            <span class="ol-tc__name" style="color: {{ $msg['color'] }}">{{ $msg['name'] }}</span>
            @if ($msg['first'])
              <span class="ol-tc__chip">first message</span>
            @endif
            <span class="ol-tc__body">{{ $msg['text'] }}@if ($msg['emote']) <span class="ol-tc__emote">{{ $msg['emote'] }}</span>@endif</span>
          </div>
        @endforeach
        <div class="ol-tc__ticker">
          <b>LIVE CHAT</b>
          <div>
            <p>
              @foreach ($chatMessages as $msg)
                <span class="ol-tc__name" style="color: {{ $msg['color'] }}">{{ $msg['name'] }}</span>
                <span class="ol-tc__body">{{ $msg['text'] }}@if ($msg['emote']) <span class="ol-tc__emote">{{ $msg['emote'] }}</span>@endif</span>
              @endforeach
            </p>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
