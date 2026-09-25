{{--
  The fold between the streamer half of the page and the builder half. Every
  section below this one (tags, controls, conditionals, events, integrations,
  kits, the testing page) is the original homepage, kept whole for the people
  who do want to write their own and for the search terms it already ranks
  on. This section only introduces it, and makes the one claim that fold can
  make and nobody else can: an assistant can write the overlay for you.
--}}
<section id="build" class="scroll-mt-16 border-b border-b-sidebar-border bg-card py-20 sm:py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Build your own</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Want something nobody has made yet?</h2>
      <p class="mb-4 max-w-2xl text-lg text-foreground">
        Every product above is a normal Overlabels overlay: a web page with live Twitch data in it. You can copy
        one and change it, or start from nothing. Everything below this line is for that.
      </p>
      <p class="mb-10 max-w-2xl text-lg text-foreground">
        <strong>You do not have to write it yourself.</strong> Give Claude or ChatGPT the link
        <a href="/llms.txt" class="font-mono text-sky-500 hover:underline cursor-pointer">overlabels.com/llms.txt</a>,
        describe the overlay you want in plain words, and paste what it gives you into your account. The guide is
        complete, so the assistant already knows every tag, control and rule.
      </p>

      <div class="grid gap-6 sm:grid-cols-3">
        <a href="/help/llms-txt" class="collection-row block border border-sidebar-border bg-sidebar-accent p-6 cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-4 h-8 w-8 text-sky-500"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
          <h3 class="mb-2 font-semibold">Ask an assistant</h3>
          <p class="text-sm text-foreground">How to hand the guide to Claude or ChatGPT and what to ask for.</p>
        </a>
        <a href="/help" class="collection-row block border border-sidebar-border bg-sidebar-accent p-6 cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-4 h-8 w-8 text-sky-500"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
          <h3 class="mb-2 font-semibold">Read the docs</h3>
          <p class="text-sm text-foreground">Guides, tutorials and the full tag reference, all searchable.</p>
        </a>
        <a href="{{ route('products.index') }}" class="collection-row block border border-sidebar-border bg-sidebar-accent p-6 cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-4 h-8 w-8 text-sky-500"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
          <h3 class="mb-2 font-semibold">Start from a product</h3>
          <p class="text-sm text-foreground">Install one, open its overlay in the editor, and make it yours.</p>
        </a>
      </div>
    </div>
  </div>
</section>
