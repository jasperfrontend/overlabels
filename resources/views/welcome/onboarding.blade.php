<section class="border-b border-b-sidebar-border py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Getting started</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">The Onboarding Wizard</h2>
      <p class="mb-12 max-w-2xl text-lg text-foreground">
        After signing up, the system will trigger an onboarding wizard which will set you up with the defaults you need to
        make Overlabels work for you: One overlay, a bunch of alerts and your secret token is generated and applied to the URL
        you need to add to your OBS. We also generate your personal template tags that match the level of your Twitch account.
        This so you don't end up with affiliate level capabilities if you're a Twitch partner and vice versa.
      </p>

      <div class="grid gap-10 sm:grid-cols-2">
        <div>
          <h3 class="mb-5 flex items-center gap-2 text-sm font-semibold tracking-widest text-foreground uppercase">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-sky-500"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
            Automated on signup
          </h3>
          <ul class="space-y-4 text-sm">
            <li class="flex items-start gap-3">
              <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">01</span>
              <span class="text-foreground">Secure webhook connection configured</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">02</span>
              <span class="text-foreground">Starter kit copied into your account</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">03</span>
              <span class="text-foreground">Alerts mapped to events automatically</span>
            </li>
            <li class="flex items-start gap-3">
              <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">04</span>
              <span class="text-foreground">Tag set generated from your Twitch data</span>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="mb-5 flex items-center gap-2 text-sm font-semibold tracking-widest text-foreground uppercase">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-sky-500"><path d="m18 16 4-4-4-4"/><path d="m6 8-4 4 4 4"/><path d="m14.5 4-5 16"/></svg>
            Personalised testing page
          </h3>
          <p class="mb-4 text-sm text-foreground">
            The <code class="font-mono text-violet-400">/testing</code> page generates ready-to-run Twitch CLI commands for your account.
            Trigger events locally and verify your overlay without going live.
          </p>
          <p class="text-sm text-foreground">
            <strong>Example:</strong> simulate a new follower event
          </p>
          <div class="overflow-x-auto rounded-sm bg-sidebar-accent p-4 font-mono text-xs leading-6">
            <div><span class="text-zinc-500">$ twitch event trigger channel.follow \</span></div>
            <div><span class="text-zinc-500">&nbsp;&nbsp;--transport=webhook \</span></div>
            <div>
              <span class="text-zinc-500">&nbsp;&nbsp;-F </span><span class="text-emerald-600 dark:text-emerald-400">https://overlabels.com/api/twitch/webhook</span><span class="text-zinc-500"> \</span>
            </div>
            <div>
              <span class="text-zinc-500">&nbsp;&nbsp;-s </span><span class="text-amber-700 dark:text-amber-300">your_webhook_secret</span><span class="text-zinc-500"> \</span>
            </div>
            <div><span class="text-zinc-500">&nbsp;&nbsp;--to-user </span><span class="text-amber-700 dark:text-amber-300">your_twitch_id</span></div>
            <div><span class="text-zinc-500">&nbsp;&nbsp;--from-user </span><span class="text-amber-700 dark:text-amber-300">another_twitch_id</span></div>
          </div>
          <p class="mt-3 text-xs text-foreground">You'll need to have
            <a href="https://dev.twitch.tv/docs/cli/" class="text-sky-500 hover:text-sky-500 hover:underline cursor-pointer" target="_blank" rel="noopener">Twitch CLI</a> installed for this to work.</p>
        </div>
      </div>
    </div>
  </div>
</section>
