{{--
  Twitch Chat Plinko, for real: resources/js/welcome/plinkoDemo.ts mounts the
  product's own board (resources/js/plinko/plinkoBoard.ts, the same physics
  and scoreboard the overlay runs) into the scene, made-up viewers type !drop
  in the chat column, and the chat box under the scene lets the visitor drop
  their own ball and chase the top score. Nothing is sent anywhere.

  What is rendered here is the fallback: the product's hero artwork. It is
  what shows before the board chunk arrives, and what stays when it cannot.
  The chat box is hidden until the board is live.
--}}
<div class="ol-demo-frame overflow-hidden rounded-sm border border-sidebar-border" data-plinko-demo>
  {{-- The product page passes bare: it has its own heading, and a pretend OBS title bar there reads as filler. --}}
  @unless ($bare ?? false)
  <div class="flex items-center gap-2 border-b border-sidebar-border bg-card/50 px-4 py-2.5">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
    <span class="font-mono text-xs text-muted-foreground">Twitch Chat Plinko, live in OBS</span>
  </div>
  @endunless
  <div class="ol-demo ol-pk" aria-label="Twitch Chat Plinko demo: viewers type !drop in chat and a ball with their name bounces down a Plinko board">
    <div class="ol-pk__chat" data-plinko-demo-chat aria-hidden="true"></div>
    <div class="ol-pk__board ol-plinko" data-plinko-demo-board aria-hidden="true"></div>
    <img
      class="ol-pk__fallback"
      data-plinko-demo-fallback
      src="/products/twitch-chat-plinko-hero.jpg"
      alt="Twitch Chat Plinko on stream: balls carrying chatters' names bounce down a Plinko board into slots worth 5 to 1000 points"
      width="1280"
      height="720"
      loading="lazy"
    />
  </div>
  <form class="ol-ck__prompt" data-plinko-demo-form hidden>
    <label for="plinko-demo-input" class="ol-ck__prompt-hint">You are in chat now. Type !drop and your name rides the ball. One ball each, so wait for yours to land.</label>
    <div class="ol-ck__prompt-row">
      <input
        id="plinko-demo-input"
        class="ol-ck__input"
        data-plinko-demo-input
        type="text"
        value="!drop"
        placeholder="!drop"
        maxlength="120"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        enterkeyhint="send"
      />
      <button type="submit" class="btn btn-primary cursor-pointer">Send</button>
    </div>
  </form>
</div>
