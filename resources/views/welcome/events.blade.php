@php
    $twitchEvents = [
        ['type' => 'channel.follow', 'label' => 'New Follower', 'tag' => 'event.user_name'],
        ['type' => 'channel.subscribe', 'label' => 'New Subscription', 'tag' => 'event.tier'],
        ['type' => 'channel.subscription.gift', 'label' => 'Gift Subscriptions', 'tag' => 'event.total'],
        ['type' => 'channel.subscription.message', 'label' => 'Resubscription', 'tag' => 'event.message.text'],
        ['type' => 'channel.cheer', 'label' => 'Bits Cheer', 'tag' => 'event.bits'],
        ['type' => 'channel.raid', 'label' => 'Incoming Raid', 'tag' => 'event.viewers'],
        ['type' => 'channel.channel_[...]_redemption.add', 'label' => 'Channel Points', 'tag' => 'event.reward.title'],
        ['type' => 'stream.online', 'label' => 'Stream Online', 'tag' => 'event.type'],
        ['type' => 'stream.offline', 'label' => 'Stream Offline', 'tag' => ''],
    ];

    $alertPipelineSteps = [
        'Twitch sends a webhook POST to /api/twitch/webhook',
        'HMAC-SHA256 signature validated against your per-user webhook secret',
        'Mapping lookup finds the template assigned to the event type for your account',
        'Current overlay data merged with the event payload (event.viewers, event.user_name, etc.)',
        'Compiled alert broadcast to Pusher channel alerts.{twitch_id}',
        'Overlay receives the payload, renders into the alert DOM node, plays transition',
        'Auto-dismisses after configured duration. Static overlay continues uninterrupted.',
    ];
@endphp
<section id="events" class="scroll-mt-16 border-b border-sidebar-accent bg-sidebar-accent py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Event Alerts</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Every Twitch event. One syntax.</h2>
      <p class="mb-12 max-w-2xl text-lg text-foreground">
        Assign an alert template to any EventSub event. When the event fires, Overlabels renders the template with
        the payload merged into the tag
        context, broadcasts the compiled alert to your overlay over WebSocket, and displays it with a configured
        transition and duration — all
        without any interaction from you.
      </p>

      <!-- Events grid -->
      <div class="mb-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($twitchEvents as $evt)
          <div class="rounded-sm border border-sidebar-accent bg-card p-4 w-full">
            <div class="mb-1 text-sm font-semibold">{{ $evt['label'] }}</div>
            <div class="mb-3 max-w-full overflow-x-hidden font-mono text-xs text-muted-foreground">{{ $evt['type'] }}</div>
            @if ($evt['tag'])
              <div class="rounded bg-accent px-2.5 py-1.5 font-mono text-xs text-amber-700 dark:text-amber-300">
                [[[{{ $evt['tag'] }}]]]
              </div>
            @else
              <div class="rounded bg-sidebar-accent px-2.5 py-1.5 font-mono text-xs text-zinc-600">no payload</div>
            @endif
          </div>
        @endforeach
      </div>

      <!-- Alert pipeline -->
      <div class="overflow-hidden rounded-sm max-w-3xl hover:max-w-full transition-all">
        <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
          <span class="font-mono text-xs text-muted-foreground">What happens when a raid fires</span>
        </div>
        <div class="divide-y divide-border/50">
          @foreach ($alertPipelineSteps as $i => $step)
            <div class="flex items-start gap-4 bg-card px-5 py-3.5 transition-colors hover:bg-muted/30">
              <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500/70">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
              <span class="text-sm text-foreground">{{ $step }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>
