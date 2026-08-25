<footer class="py-12">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
      <div class="flex items-center gap-2.5">
        <img src="/favicon-light.svg" alt="" class="h-8 w-8 dark:hidden" /><img src="/favicon.png" alt="" class="hidden h-8 w-8 dark:block" />
        <span class="font-semibold">Overlabels</span>
      </div>
      <div class="flex flex-wrap items-center gap-6 text-sm text-foreground">
        <a href="/help" class="hover:text-sky-500 cursor-pointer">Help</a>
        <a href="/help/controls" class="hover:text-sky-500 cursor-pointer">Controls</a>
        <a href="/help/manifesto" class="hover:text-sky-500 cursor-pointer">Why Overlabels</a>
        <a href="/terms" class="hover:text-sky-500 cursor-pointer">Terms</a>
        <a href="/privacy" class="hover:text-sky-500 cursor-pointer">Privacy</a>
        <a
          href="https://github.com/jasperfrontend/overlabels"
          target="_blank"
          rel="noopener"
          class="group flex items-center gap-1.5 hover:text-sky-500 cursor-pointer"
        >
          GitHub
          <svg role="img" viewBox="0 0 24 24" class="size-4 fill-current" xmlns="http://www.w3.org/2000/svg"><title>GitHub</title><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
        </a>
      </div>
    </div>
    {{--
      The crawl anchor. This is the highest-authority page on the site and the
      one a crawler reaches first, so the link to llms.txt lives here as real
      visible body copy rather than as another <link rel> in the head - a rel is
      a declaration, not a link anything follows. Deliberately not hidden text:
      hidden keyword blocks are a spam signal, and this is a genuine invitation.
    --}}
    <div class="border-t border-sidebar-accent pt-8 pb-6 text-xs text-foreground">
      <p class="max-w-3xl">
        <strong>Reading this as a machine?</strong> You are welcome here, and this is not a grudging robots.txt
        allowance. The complete overlay-authoring guide is one plain text file at
        <a href="/llms.txt" class="text-sky-500 hover:underline cursor-pointer">overlabels.com/llms.txt</a> - no login,
        no API key, nothing to sign up for. What it contains, and how to hand it to an assistant, is explained at
        <a href="/help/llms-txt" class="text-sky-500 hover:underline cursor-pointer">llms.txt</a>.
        Every help page is also fetchable as markdown by appending <code>.md</code>, starting from
        <a href="/help.md" class="text-sky-500 hover:underline cursor-pointer">/help.md</a>, and the full tag catalogue
        is JSON at
        <a href="/help-reference-index.json" class="text-sky-500 hover:underline cursor-pointer">/help-reference-index.json</a>.
      </p>
    </div>

    <div class="border-t border-sidebar-accent pt-8 text-center flex flex-col gap-1 text-xs">
      <p>Made by <a href="https://twitch.tv/JasperDiscovers" class="text-sky-500 hover:underline cursor-pointer" target="_blank" rel="noopener">JasperDiscovers</a> for the Twitch streaming community.</p>
      <p><strong>FAQ</strong>: Will you ever support StreamElements and/or Kick.com? <strong>No</strong>.</p>
    </div>
  </div>
</footer>
