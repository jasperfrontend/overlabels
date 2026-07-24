<nav class="sticky top-0 z-50 border-b border-sidebar-accent bg-sidebar-accent/80 backdrop-blur-lg">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex h-16 items-center justify-between">
      <a href="/" class="flex items-center gap-2.5 cursor-pointer">
        <img src="/favicon-light.svg" alt="" class="h-8 w-8 dark:hidden" /><img src="/favicon.png" alt="" class="hidden h-8 w-8 dark:block" />
        <span class="text-lg font-bold tracking-tight">Overlabels</span>
      </a>
      <div class="hidden text-foreground items-center gap-6 lg:flex">
        <a href="#tags" class="text-sm hover:text-sky-500 cursor-pointer">Tags</a>
        <a href="#controls" class="text-sm hover:text-sky-500 cursor-pointer">Controls</a>
        <a href="#conditionals" class="text-sm hover:text-sky-500 cursor-pointer">Conditionals</a>
        <a href="#events" class="text-sm hover:text-sky-500 cursor-pointer">Events</a>
        <a href="#integrations" class="text-sm hover:text-sky-500 cursor-pointer">Integrations</a>
        <a href="#kits" class="text-sm hover:text-sky-500 cursor-pointer">Kits</a>
        <a href="/help" class="text-sm hover:text-sky-500 cursor-pointer">Help</a>
        <a href="/help/manifesto" class="text-sm hover:text-sky-500 cursor-pointer">Why Overlabels</a>
        @include('welcome.theme-toggle')
        @auth
          <a href="{{ route('dashboard.index') }}" class="btn btn-primary text-sm cursor-pointer">
            Dashboard
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1.5 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </a>
        @else
          <div class="flex items-center gap-2">
            <a href="/login" class="btn btn-primary text-sm gap-2 cursor-pointer">
              <svg viewBox="0 0 24 24" fill="currentColor" class="size-4"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0 1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" /></svg>
              Connect
            </a>
          </div>
        @endauth
      </div>
      <div class="flex items-center gap-3 lg:hidden">
        @include('welcome.theme-toggle')
        <button type="button" data-mobile-menu-toggle aria-label="Toggle menu"
                class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-sm text-muted-foreground transition-colors hover:text-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" data-mobile-menu-icon="open"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden h-5 w-5" data-mobile-menu-icon="close"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
    </div>
    <div class="container mx-auto px-4 pb-3 sm:px-6 lg:hidden">
      @auth
        <a href="{{ route('dashboard.index') }}" class="btn btn-primary text-sm flex w-full justify-center cursor-pointer">
          Dashboard
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1.5 h-4 w-4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      @else
        @include('welcome.login-social', ['class' => 'flex! w-full justify-center'])
      @endauth
    </div>
  </div>
  <!-- Mobile menu -->
  <div data-mobile-menu class="hidden border-t border-sidebar-accent bg-sidebar-accent/95 backdrop-blur-lg lg:hidden">
    <div class="container mx-auto space-y-1 px-4 py-4 sm:px-6">
      @foreach ([
          ['href' => '#tags', 'label' => 'Tags'],
          ['href' => '#controls', 'label' => 'Controls'],
          ['href' => '#conditionals', 'label' => 'Conditionals'],
          ['href' => '#events', 'label' => 'Events'],
          ['href' => '#integrations', 'label' => 'Integrations'],
          ['href' => '#kits', 'label' => 'Kits'],
      ] as $item)
        <a href="{{ $item['href'] }}" data-mobile-menu-link
           class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer">{{ $item['label'] }}</a>
      @endforeach
      <div class="my-2 border-t border-sidebar-accent"></div>
      <a href="/help" data-mobile-menu-link
         class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer">Help</a>
      <a href="/help/manifesto" data-mobile-menu-link
         class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer">Why Overlabels</a>
    </div>
  </div>
</nav>
