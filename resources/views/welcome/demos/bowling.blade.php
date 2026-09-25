{{--
  Follower Bowling, as it looks in OBS: the queue panel fed by the lane
  List on the left, the lane on the right with the ten newest followers
  racked as pins. One throw per loop: the bowler leaves the line, the ball
  goes, eight pins fall, the score shows, someone new joins with !bowl, the
  rack resets. Pure CSS (resources/css/welcome-demos.css), 10 second loop.
  The real overlay puts follower avatars on the pins; here it is initials.
--}}
@php
    $bowlPins = ['moss_ttv', 'pixelmoth', 'tea_and_raids', 'noodlebyte', 'quietfox', 'kettle_', 'dana_plays', 'sunroof', 'bytesized', 'rivermoss'];
@endphp
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border">
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Follower Bowling, live in OBS</span>
  </div>
  <div class="ol-demo ol-bowl" aria-label="Follower Bowling demo: viewers type !bowl to get in line, the ball rolls, the newest followers fall as pins">
    <div class="ol-bowl__queue" aria-hidden="true">
      <div class="ol-bowl__qhead"><span>In line</span><span class="ol-bowl__qcount"><span>3</span><span>2</span><span>3</span></span></div>
      <div class="ol-bowl__qname ol-bowl__qname--next">moss_ttv</div>
      <div class="ol-bowl__qname">dana_plays</div>
      <div class="ol-bowl__qname">sunroof</div>
      <div class="ol-bowl__qname">kettle_</div>
      <div class="ol-bowl__qjoin"><span class="ol-demo__cmd">!bowl</span> to join</div>
    </div>
    <div class="ol-bowl__lane" aria-hidden="true">
      <div class="ol-bowl__score">8 PINS</div>
      <div class="ol-bowl__bowler">moss_ttv bowls</div>
      <div class="ol-bowl__ball"></div>
      <div class="ol-bowl__pins">
        @foreach ($bowlPins as $pin)
          <div class="ol-bowl__pin"><i>{{ strtoupper(substr($pin, 0, 1)) }}</i><span>{{ $pin }}</span></div>
        @endforeach
      </div>
    </div>
  </div>
</div>
