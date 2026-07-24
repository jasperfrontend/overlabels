<section class="border-b border-sidebar-accent py-24 sm:py-36">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">

      <h1 class="mb-6 text-5xl leading-[1.05] font-bold tracking-tight sm:text-6xl md:text-7xl">
        Your <svg viewBox="0 0 24 24" fill="#9146FF" class="inline-block h-[0.85em] w-[0.85em] align-[-0.08em] mx-1"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0 1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" /></svg> overlay is a webpage.<br />
        <span class="text-sky-500">We make it reactive.</span>
      </h1>

      <p class="mb-4 max-w-2xl text-xl leading-relaxed text-foreground">
        Template tags, a reactive expression engine, and pipe formatters wired into the HTML and CSS you already write.
        Pull live Twitch data with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-amber-700 dark:text-amber-400">[[[tag]]]</code>.
        Derive state with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-amber-700 dark:text-amber-400">c.wins / (c.wins + c.losses) * 100</code>.
        React to follows, raids, and donations from Ko-fi, Streamlabs, StreamElements, Buy Me A Coffee, FourthWall and Throne. Update anything from your dashboard and watch the overlay catch up in milliseconds.
      </p>
      <p class="mb-14 max-w-2xl text-base text-foreground">
        No drag-and-drop editor. No proprietary file format. No lock-in. <strong>Overlabels is the reactive substrate - your overlay is just a webpage it keeps alive.</strong>
      </p>

      <!-- Hero code blocks -->
      <div class="mb-14 grid gap-4 lg:grid-cols-2">
        <div class="overflow-hidden rounded-sm">
          <div class="flex items-center gap-2 border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
            <span class="font-mono text-xs text-muted-foreground">overlay.html</span>
          </div>
          <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
            <div>
              <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"stat-bar"</span><span class="text-zinc-500">&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span><span class="text-zinc-500">&lt;/span&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span class="text-foreground">followers</span><span class="text-zinc-500">&lt;/small&gt;</span>
            </div>
            <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
            <div class="mt-2"></div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[if:followers_total &gt;= 1000]]]</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"milestone"</span><span class="text-zinc-500">&gt;</span>
            </div>
            <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-foreground">four digits. let's go.</span></div>
            <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
            <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
          </div>
        </div>

        <div class="overflow-hidden rounded-sm bg-emerald-50 dark:bg-emerald-950 ">
          <div class="flex items-center gap-2 border-b border-emerald-500/20 px-4 py-2.5">
            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-600 dark:bg-emerald-500"></span>
            <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">live in OBS</span>
          </div>
          <div class="overflow-x-auto bg-emerald-50 dark:bg-emerald-950 p-5 font-mono text-sm leading-7">
            <div>
              <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"stat-bar"</span><span class="text-zinc-500">&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-emerald-600 dark:text-emerald-300">1,342</span><span class="text-zinc-500">&lt;/span&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span class="text-foreground">followers</span><span class="text-zinc-500">&lt;/small&gt;</span>
            </div>
            <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
            <div class="mt-2"></div>
            <div>
              <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"milestone"</span><span class="text-zinc-500">&gt;</span>
            </div>
            <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-foreground">four digits. let's go.</span></div>
            <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-4">
        @auth
          <a href="{{ route('dashboard.index') }}" class="cursor-pointer">
            <button class="btn btn-primary cursor-pointer">Go to dashboard
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
          </a>
        @else
          <a href="#get-started" class="cursor-pointer">
            <button class="btn btn-primary cursor-pointer">Get started free
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
          </a>
        @endauth
        <a href="/help/manifesto" class="cursor-pointer">
          <button class="btn btn-secondary cursor-pointer">Read the manifesto</button>
        </a>
      </div>
    </div>
  </div>
</section>
