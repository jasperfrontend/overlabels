@php
    $controlTypes = [
        [
            'type' => 'text',
            'icon' => '<path d="M12 4v16"/><path d="M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2"/><path d="M9 20h6"/>',
            'description' => 'Strings up to 1000 chars. HTML stripped on save.',
            'example' => '[[[c:myname]]] → JasperDiscovers',
        ],
        [
            'type' => 'number',
            'icon' => '<line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>',
            'description' => 'Numeric values. Safely coerced, defaults to 0.',
            'example' => '[[[c:goal_target]]] → 500',
        ],
        [
            'type' => 'counter',
            'icon' => '<path d="M10 5H3"/><path d="M12 19H3"/><path d="M14 3v4"/><path d="M16 17v4"/><path d="M21 12h-9"/><path d="M21 19h-5"/><path d="M21 5h-7"/><path d="M8 10v4"/><path d="M8 12H3"/>',
            'description' => 'Integer counter. Increment or decrement from the dashboard.',
            'example' => '[[[c:kill_count]]] → 14',
        ],
        [
            'type' => 'timer',
            'icon' => '<line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/>',
            'description' => 'Countup or countdown at 250ms resolution. State broadcast over WebSocket.',
            'example' => '[[[c:round_timer]]] → 4:32',
        ],
        [
            'type' => 'datetime',
            'icon' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
            'description' => 'ISO 8601 datetime. For scheduled events, stream start times.',
            'example' => '[[[c:next_event]]] → 2026-03-01T20:00:00Z',
        ],
        [
            'type' => 'boolean',
            'icon' => '<circle cx="9" cy="12" r="3"/><rect width="20" height="14" x="2" y="5" rx="7"/>',
            'description' => 'Stores "1" or "0". Toggle overlay sections live from your dashboard.',
            'example' => '[[[if:c:show_goal]]] → show or hide',
        ],
    ];
@endphp
<section id="controls" class="scroll-mt-16 border-b border-sidebar-accent bg-sidebar-accent py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Controls</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Typed, mutable overlay state.</h2>
      <p class="mb-3 max-w-2xl text-lg text-foreground">
        Controls are named, typed values you define per template and update from your dashboard while the overlay is
        live in OBS. Change a value
        and your overlay re-renders the new data near-instantly. All without page reloads, of course!
      </p>
      <p class="mb-12 max-w-2xl text-foreground">
        Reference any control with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">[[[c:key]]]</code>
        — in HTML,
        in CSS, and in conditional blocks.
        <a href="/help/controls" class="ml-1 text-sky-500 hover:underline cursor-pointer">Full controls reference →</a>
      </p>

      <!-- Control types grid -->
      <div class="mb-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($controlTypes as $ctrl)
          <div class="rounded-sm bg-card p-4">
            <div class="mb-2 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-sky-500">{!! $ctrl['icon'] !!}</svg>
              <span class="font-mono text-sm font-semibold">{{ $ctrl['type'] }}</span>
            </div>
            <p class="mb-3 text-sm text-muted-foreground">{{ $ctrl['description'] }}</p>
            <div class="overflow-x-auto rounded bg-accent px-3 py-1.5 font-mono text-xs text-amber-700 dark:text-amber-400">
              {{ $ctrl['example'] }}
            </div>
          </div>
        @endforeach
      </div>

      <!-- Power combo -->
      <div class="overflow-hidden rounded-sm max-w-3xl hover:max-w-full transition-all">
        <div class="border-b border-sky-500/20 bg-sky-400/10 dark:bg-sky-950/20 px-4 py-2.5">
          <span class="font-mono text-xs text-sky-600 dark:text-sky-400">Power combo — boolean control + countdown timer + conditional class binding</span>
        </div>
        <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
          <div>
            <span class="text-xs text-zinc-600">// "show_timer" → boolean → "1" &nbsp;&nbsp; "round_timer" → timer → countdown, 300s base</span>
          </div>
          <div class="mt-2"></div>
          <div><span class="text-sky-600 dark:text-sky-400">[[[if:c:show_timer]]]</span></div>
          <div>
            &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"timer </span><span class="text-sky-600 dark:text-sky-400">[[[if:c:round_timer &lt;= 10]]]</span><span class="text-emerald-600 dark:text-emerald-400">danger</span><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span><span class="text-emerald-600 dark:text-emerald-400">"</span><span class="text-zinc-500">&gt;</span>
          </div>
          <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">[[[c:round_timer]]]</span></div>
          <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
          <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
        </div>
      </div>
      <p class="mt-3 text-sm max-w-3xl text-muted-foreground">
        The timer ticks at 250ms resolution. The <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-amber-700 dark:text-amber-400">danger</code>
        class applies
        automatically when the countdown reaches 10 seconds. Flip the boolean from the dashboard to show or hide the
        block, with near-live updates.
      </p>
    </div>
  </div>
</section>
