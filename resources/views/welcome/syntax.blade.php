<section id="tags" class="scroll-mt-16 border-b border-sidebar-accent py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl" data-tabs="syntax">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Tags</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Simple tags. That’s it.</h2>
      <p class="mb-12 max-w-2xl text-lg text-foreground">
        Use a simple tag format to pull in live Twitch data. It works in HTML, in CSS, and inside show/hide rules.
        Easy to read, easy to scan.
      </p>

      <!-- Tabs -->
      <div class="mb-8 flex gap-0 border-b border-sidebar-accent">
        <button type="button" data-tab="static"
                class="-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors border-sky-500 text-sky-500">
          Live data
        </button>
        <button type="button" data-tab="css"
                class="-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors border-transparent text-muted-foreground hover:text-foreground">
          Live CSS
        </button>
        <button type="button" data-tab="events"
                class="-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors border-transparent text-muted-foreground hover:text-foreground">
          Alerts
        </button>
      </div>

      <div data-tab-panel="static">
        <div class="mb-4 overflow-hidden rounded-sm border border-sidebar-accent max-w-3xl hover:max-w-full transition-all">
          <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
            <span class="font-mono text-xs text-muted-foreground">Overlay example — subscriber bar</span>
          </div>
          <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
            <div>
              <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"sub-bar"</span><span class="text-zinc-500">&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-700 dark:text-amber-400">[[[subscribers_total]]]</span><span class="text-foreground"> subs</span><span class="text-zinc-500">&lt;/span&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-foreground">Latest: </span><span class="text-amber-700 dark:text-amber-400">[[[subscribers_latest_user_name]]]</span><span class="text-zinc-500">&lt;/span&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-700 dark:text-amber-400">[[[channel_game]]]</span><span class="text-foreground"> | </span><span class="text-amber-700 dark:text-amber-400">[[[channel_title]]]</span><span class="text-zinc-500">&lt;/span&gt;</span>
            </div>
            <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
          </div>
        </div>
        <p class="text-sm text-muted-foreground">
          Tags cover your channel, followers, subs, goals, and more.
          <a href="/help/conditionals" class="text-sky-500 hover:underline cursor-pointer">Browse all template tags →</a>
        </p>
      </div>

      <div data-tab-panel="css" class="hidden">
        <div class="mb-4 overflow-hidden rounded-sm border border-sidebar-accent max-w-3xl hover:max-w-full transition-all">
          <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">overlay.css — live values can be used inside CSS</span>
          </div>
          <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
            <div><span class="text-sky-600 dark:text-sky-400">.follower-bar</span><span class="text-zinc-500"> &#123;</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-600 dark:text-zinc-400">width</span><span class="text-zinc-500">: calc(</span><span class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span><span class="text-zinc-500"> / </span><span class="text-amber-700 dark:text-amber-400">[[[goals_latest_target]]]</span><span class="text-zinc-500"> * 100%);</span>
            </div>
            <div><span class="text-zinc-500">&#125;</span></div>
            <div class="mt-3"></div>
            <div><span class="text-sky-600 dark:text-sky-400">.stream-title::before</span><span class="text-zinc-500"> &#123;</span></div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-600 dark:text-zinc-400">content</span><span class="text-zinc-500">: </span><span class="text-emerald-600 dark:text-emerald-400">"</span><span class="text-amber-700 dark:text-amber-400">[[[channel_title]]]</span><span class="text-emerald-600 dark:text-emerald-400">"</span><span class="text-zinc-500">;</span>
            </div>
            <div><span class="text-zinc-500">&#125;</span></div>
          </div>
        </div>
        <p class="text-sm text-muted-foreground">
          Dynamic widths, generated content, colour values driven by data — anything a CSS value can express, a tag
          can provide.
        </p>
      </div>

      <div data-tab-panel="events" class="hidden">
        <div class="mb-4 overflow-hidden rounded-sm border border-sidebar-accent max-w-3xl hover:max-w-full transition-all">
          <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
            <span class="font-mono text-xs text-muted-foreground">Alert template — channel.follow</span>
          </div>
          <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
            <div>
              <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"follow-alert"</span><span class="text-zinc-500">&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;h1&gt;</span><span class="text-amber-700 dark:text-amber-400">[[[event.user_name]]]</span><span class="text-foreground"> just followed!</span><span class="text-zinc-500">&lt;/h1&gt;</span>
            </div>
            <div>
              &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Follower #</span><span class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span><span class="text-zinc-500">&lt;/p&gt;</span>
            </div>
            <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
            <div class="mt-2 text-xs text-muted-foreground/60">
              <span class="text-zinc-500">&lt;!--</span> First ever: wilko_dj <span class="text-zinc-500">--&gt;</span>
            </div>
          </div>
        </div>
        <p class="text-sm text-muted-foreground max-w-3xl">
          Event tags are merged with your static overlay data at render time. All static tags remain available
          inside alert templates. You're encouraged to mix them freely.
        </p>
      </div>
    </div>
  </div>
</section>
