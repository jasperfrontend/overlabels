{{--
  Chat Tower, as it looks in OBS. Chat on the left types !stack, or !stack
  left / right to throw a block further out on that side; the tower on the
  right grows, sways, and falls. Pure CSS (resources/css/welcome.css), loops
  every 16 seconds. The schedule here mirrors the generated stylesheet: same
  order, same aims.
--}}
@php
    $towerChat = [
        ['name' => 'pixelmoth', 'color' => '#1E90FF', 'aim' => null],
        ['name' => 'tea_and_raids', 'color' => '#FF7F50', 'aim' => null],
        ['name' => 'noodlebyte', 'color' => '#9ACD32', 'aim' => 'left'],
        ['name' => 'quietfox', 'color' => '#8A2BE2', 'aim' => null],
        ['name' => 'kettle_', 'color' => '#FF69B4', 'aim' => 'right'],
        ['name' => 'moss_ttv', 'color' => '#5F9EA0', 'aim' => null],
        ['name' => 'dana_plays', 'color' => '#DAA520', 'aim' => 'right'],
        ['name' => 'sunroof', 'color' => '#2E8B57', 'aim' => 'left'],
        ['name' => 'bytesized', 'color' => '#FF4500', 'aim' => 'right'],
    ];
@endphp
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border">
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Chat Tower, live in OBS</span>
  </div>
  <div class="ol-tower-demo" aria-label="Chat Tower demo: viewers type !stack in chat, the tower grows, sways and falls">
    <div class="ol-tower-demo__chat" aria-hidden="true">
      @foreach ($towerChat as $line)
        <div class="ol-tower-demo__line">
          <span class="ol-tower-demo__name" style="color: {{ $line['color'] }}">{{ $line['name'] }}</span>:
          <span class="ol-tower-demo__cmd">!stack{{ $line['aim'] ? ' '.$line['aim'] : '' }}</span>
        </div>
      @endforeach
      <div class="ol-tower-demo__line ol-tower-demo__bot">
        <span class="ol-tower-demo__name">overlabels</span>: The tower fell at 9. bytesized placed the last block. Record stands at 44.
      </div>
    </div>
    <div class="ol-tower-demo__stage" aria-hidden="true">
      <div class="ol-tower-demo__fall ol-tower-demo__fall--l"></div>
      <div class="ol-tower-demo__fall ol-tower-demo__fall--r"></div>
      <div class="ol-tower-demo__tower">
        @foreach ($towerChat as $line)
          <div class="ol-tower-demo__block"></div>
        @endforeach
      </div>
      <div class="ol-tower-demo__ground"></div>
    </div>
  </div>
</div>
