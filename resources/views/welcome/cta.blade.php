<section id="get-started" class="border-b border-sidebar-accent py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="mb-6 text-4xl font-bold tracking-tight sm:text-5xl">
        Ship your overlay.<br />
        <span class="text-sky-500">Free. Forever.</span>
      </h2>
      <p class="mx-auto mb-10 max-w-lg text-lg text-foreground">
        No paywalls. No tiers. No artificial limits. Everything you create is yours. The whole thing is open source.
      </p>

      @auth
        <div class="flex flex-col items-center gap-4">
          <a href="{{ route('dashboard.index') }}" class="btn btn-primary px-8 text-base cursor-pointer"> Go to Dashboard
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2 h-5 w-5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </a>
        </div>
      @else
        <div class="flex flex-col items-center gap-6">
          @include('welcome.login-social')
          <p class="text-xs text-foreground">
            Connect with Twitch to log in to Overlabels.
            Revoke access anytime from your <a href="https://www.twitch.tv/settings/connections" class="text-sky-400 hover:underline cursor-pointer">Twitch settings</a>.
          </p>
        </div>
      @endauth
    </div>
  </div>
</section>
