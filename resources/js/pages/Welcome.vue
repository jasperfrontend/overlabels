<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
  AlertTriangle,
  ArrowRight,
  Zap,
  ToggleLeft,
  Timer,
  Hash,
  Type,
  Calendar,
  SlidersHorizontal,
  GitFork,
  Layers,
  Code2,
  Shield,
  Heart,
  DollarSign,
  Menu,
  X
} from 'lucide-vue-next';
import { ref } from 'vue';

const syntaxTab = ref('static');
const integrationsTab = ref<'kofi' | 'streamlabs' | 'streamelements'>('kofi');
const mobileMenuOpen = ref(false);

const integrationConfigs = {
  kofi: {
    name: 'Ko-fi',
    namespace: 'kofi',
    tagline: 'Donations, subscriptions, shop orders',
    description:
      'Paste your Ko-fi verification token, set your webhook URL, done. Every Ko-fi event flows through the same alert pipeline as Twitch events.',
  },
  streamlabs: {
    name: 'Streamlabs',
    namespace: 'streamlabs',
    tagline: 'Live donation tracking via OAuth',
    description:
      'One click to authenticate. Overlabels listens for donations in real time and auto-provisions six controls the moment you connect.',
  },
  streamelements: {
    name: 'StreamElements',
    namespace: 'streamelements',
    tagline: 'Live donation tracking via JWT',
    description:
      'Paste your JWT token to authenticate. Overlabels listens for donations in real time and auto-provisions six controls the moment you connect.',
  },
} as const;

const controlTypes = [
  {
    type: 'text',
    icon: Type,
    description: 'Strings up to 1000 chars. HTML stripped on save.',
    example: '[[[c:myname]]] → JasperDiscovers'
  },
  {
    type: 'number',
    icon: Hash,
    description: 'Numeric values. Safely coerced, defaults to 0.',
    example: '[[[c:goal_target]]] → 500'
  },
  {
    type: 'counter',
    icon: SlidersHorizontal,
    description: 'Integer counter. Increment or decrement from the dashboard.',
    example: '[[[c:kill_count]]] → 14'
  },
  {
    type: 'timer',
    icon: Timer,
    description: 'Countup or countdown at 250ms resolution. State broadcast over WebSocket.',
    example: '[[[c:round_timer]]] → 4:32'
  },
  {
    type: 'datetime',
    icon: Calendar,
    description: 'ISO 8601 datetime. For scheduled events, stream start times.',
    example: '[[[c:next_event]]] → 2026-03-01T20:00:00Z'
  },
  {
    type: 'boolean',
    icon: ToggleLeft,
    description: 'Stores "1" or "0". Toggle overlay sections live from your dashboard.',
    example: '[[[if:c:show_goal]]] → show or hide'
  }
];

const twitchEvents = [
  { type: 'channel.follow', label: 'New Follower', tag: 'event.user_name' },
  { type: 'channel.subscribe', label: 'New Subscription', tag: 'event.tier' },
  { type: 'channel.subscription.gift', label: 'Gift Subscriptions', tag: 'event.total' },
  { type: 'channel.subscription.message', label: 'Resubscription', tag: 'event.message.text' },
  { type: 'channel.cheer', label: 'Bits Cheer', tag: 'event.bits' },
  { type: 'channel.raid', label: 'Incoming Raid', tag: 'event.viewers' },
  { type: 'channel.channel_[...]_redemption.add', label: 'Channel Points', tag: 'event.reward.title' },
  { type: 'stream.online', label: 'Stream Online', tag: 'event.type' },
  { type: 'stream.offline', label: 'Stream Offline', tag: '' }
];

const alertPipelineSteps = [
  'Twitch sends a webhook POST to /api/twitch/webhook',
  'HMAC-SHA256 signature validated against your per-user webhook secret',
  'Mapping lookup finds the template assigned to the event type for your account',
  'Current overlay data merged with the event payload (event.viewers, event.user_name, etc.)',
  'Compiled alert broadcast to Pusher channel alerts.{twitch_id}',
  'Overlay receives the payload, renders into the alert DOM node, plays transition',
  'Auto-dismisses after configured duration. Static overlay continues uninterrupted.'
];
</script>

<template>
  <div class="min-h-screen bg-sidebar-accent text-foreground">
    <Head>
      <title>Overlabels - Reactive Twitch overlays for people who code</title>
      <meta
        name="description"
        content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source."
      />

      <!-- Open Graph -->
      <meta property="og:type" content="website" />
      <meta property="og:url" content="https://overlabels.com/" />
      <meta property="og:site_name" content="Overlabels" />
      <meta property="og:title" content="Overlabels • Reactive Twitch overlays for people who code" />
      <meta
        property="og:description"
        content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source."
      />
      <meta property="og:image"
            content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
      <meta property="og:image:width" content="1200" />
      <meta property="og:image:height" content="630" />
      <meta property="og:image:alt" content="Overlabels • reactive Twitch overlays for people who code" />

      <!-- Twitter / X -->
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content="Overlabels • Reactive Twitch overlays for people who code" />
      <meta
        name="twitter:description"
        content="Template tags, reactive expressions, and pipe formatters on top of the HTML and CSS you already write. Live Twitch data, event alerts, and donation tracking from Ko-fi, Streamlabs, and StreamElements. Free and open source."
      />
      <meta name="twitter:image"
            content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
      <meta name="twitter:image:alt" content="Overlabels • reactive Twitch overlays for people who code" />
    </Head>

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 border-b border-sidebar-accent bg-sidebar-accent/80 backdrop-blur-lg">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <Link href="/" class="flex items-center gap-2.5">
            <img src="/favicon-light.svg" alt="" class="h-8 w-8 dark:hidden" /><img src="/favicon.png" alt=""
                                                                                    class="hidden h-8 w-8 dark:block" />
            <span class="text-lg font-bold tracking-tight">Overlabels</span>
            <Badge variant="outline" class="text-xs">Beta</Badge>
          </Link>
          <div class="hidden text-foreground items-center gap-6 lg:flex">
            <a href="#tags" class="text-sm hover:text-sky-500">Tags</a>
            <a href="#controls"
               class="text-sm hover:text-sky-500">Controls</a>
            <a href="#conditionals" class="text-sm hover:text-sky-500">Conditionals</a>
            <a href="#events" class="text-sm hover:text-sky-500">Events</a>
            <a href="#integrations" class="text-sm hover:text-sky-500">Integrations</a>
            <a href="#kits" class="text-sm hover:text-sky-500">Kits</a>
            <Link href="/help" class="text-sm hover:text-sky-500">Help</Link>
            <Link href="/help/manifesto" class="text-sm hover:text-sky-500">
              Why Overlabels
            </Link>
            <DarkModeToggle />
            <Link v-if="$page.props.auth.user" :href="route('dashboard.index')" class="btn btn-primary text-sm">
              Dashboard
              <ArrowRight class="ml-1.5 h-4 w-4" />
            </Link>
            <LoginSocial v-else />
          </div>
          <div class="flex items-center gap-3 lg:hidden">
            <DarkModeToggle />
            <button @click="mobileMenuOpen = !mobileMenuOpen"
                    class="flex h-9 w-9 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:text-foreground">
              <X v-if="mobileMenuOpen" class="h-5 w-5" />
              <Menu v-else class="h-5 w-5" />
            </button>
          </div>
        </div>
        <div class="container mx-auto px-4 pb-3 sm:px-6 lg:hidden">
          <Link v-if="$page.props.auth.user" :href="route('dashboard.index')"
                class="btn btn-primary text-sm flex w-full justify-center">
            Dashboard
            <ArrowRight class="ml-1.5 h-4 w-4" />
          </Link>
          <LoginSocial v-else class="flex! w-full justify-center" />
        </div>
      </div>
      <!-- Mobile menu -->
      <div v-if="mobileMenuOpen" class="border-t border-sidebar-accent bg-sidebar-accent/95 backdrop-blur-lg lg:hidden">
        <div class="container mx-auto space-y-1 px-4 py-4 sm:px-6">
          <a
            v-for="item in [
              { href: '#tags', label: 'Tags' },
              { href: '#controls', label: 'Controls' },
              { href: '#conditionals', label: 'Conditionals' },
              { href: '#events', label: 'Events' },
              { href: '#integrations', label: 'Integrations' },
              { href: '#kits', label: 'Kits' },
            ]"
            :key="item.href"
            :href="item.href"
            class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            @click="mobileMenuOpen = false"
          >{{ item.label }}</a>
          <div class="my-2 border-t border-sidebar-accent"></div>
          <Link href="/help"
                class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="mobileMenuOpen = false">Help
          </Link>
          <Link href="/help/manifesto"
                class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @click="mobileMenuOpen = false">Why Overlabels
          </Link>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="border-b border-sidebar-accent py-24 sm:py-36">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">

          <h1 class="mb-6 text-5xl leading-[1.05] font-bold tracking-tight sm:text-6xl md:text-7xl">
            Your overlay is a webpage.<br />
            <span class="text-sky-500">We make it reactive.</span>
          </h1>

          <p class="mb-4 max-w-2xl text-xl leading-relaxed text-foreground">
            Template tags, a reactive expression engine, and pipe formatters wired into the HTML and CSS you already write. Pull live Twitch data with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-amber-700 dark:text-amber-400">[[[tag]]]</code>. Derive state with <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-amber-700 dark:text-amber-400">c.wins / (c.wins + c.losses) * 100</code>. React to follows, raids, and donations from Ko-fi, Streamlabs, and StreamElements. Update anything from your dashboard and watch the overlay catch up in milliseconds.
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
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"stat-bar"</span
                ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                  class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span
                ><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span
                  class="text-foreground">followers</span
                ><span class="text-zinc-500">&lt;/small&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2"></div>
                <div><span class="text-sky-600 dark:text-sky-400">[[[if:followers_total &gt;= 1000]]]</span></div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span
                  class="text-emerald-600 dark:text-emerald-400">"milestone"</span
                ><span class="text-zinc-500">&gt;</span>
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
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"stat-bar"</span
                ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                  class="text-emerald-600 dark:text-emerald-300">1,342</span
                ><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span
                  class="text-foreground">followers</span
                ><span class="text-zinc-500">&lt;/small&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2"></div>
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"milestone"</span
                ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-foreground">four digits. let's go.</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-4">
            <Link v-if="!$page.props.auth.user" href="#get-started">
              <button class="btn btn-primary">Get started free
                <ArrowRight class="ml-2 h-4 w-4" />
              </button>
            </Link>
            <Link v-else :href="route('dashboard.index')">
              <button class="btn btn-primary">Go to dashboard
                <ArrowRight class="ml-2 h-4 w-4" />
              </button>
            </Link>
            <Link href="/help/manifesto">
              <button class="btn btn-secondary">Read the manifesto</button>
            </Link>
          </div>
        </div>
      </div>
    </section>

    <!-- 01 — The Syntax -->
    <section id="tags" class="scroll-mt-16 border-b border-sidebar-accent py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Tags</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Simple tags. That’s it.</h2>
          <p class="mb-12 max-w-2xl text-lg text-foreground">
            Use a simple tag format to pull in live Twitch data. It works in HTML, in CSS, and inside show/hide rules.
            Easy to read, easy to scan.
          </p>

          <!-- Tabs -->
          <div class="mb-8 flex gap-0 border-b border-sidebar-accent">
            <button
              @click="syntaxTab = 'static'"
              :class="[
                '-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                syntaxTab === 'static' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              Live data
            </button>
            <button
              @click="syntaxTab = 'css'"
              :class="[
                '-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                syntaxTab === 'css' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              Live CSS
            </button>
            <button
              @click="syntaxTab = 'events'"
              :class="[
                '-mb-px cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                syntaxTab === 'events' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              Alerts
            </button>
          </div>

          <div v-show="syntaxTab === 'static'">
            <div class="mb-4 overflow-hidden rounded-sm border border-sidebar-accent">
              <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
                <span class="font-mono text-xs text-muted-foreground">Overlay example — subscriber bar</span>
              </div>
              <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"sub-bar"</span
                ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                  class="text-amber-700 dark:text-amber-400">[[[subscribers_total]]]</span
                ><span class="text-foreground"> subs</span><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                  class="text-foreground">Latest: </span
                ><span class="text-amber-700 dark:text-amber-400">[[[subscribers_latest_user_name]]]</span><span
                  class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span
                  class="text-amber-700 dark:text-amber-400">[[[channel_game]]]</span
                ><span class="text-foreground"> | </span><span class="text-amber-700 dark:text-amber-400">[[[channel_title]]]</span
                ><span class="text-zinc-500">&lt;/span&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Tags cover your channel, followers, subs, goals, and more.
              <Link href="/help/conditionals" class="text-sky-500 hover:underline">Browse all template tags →</Link>
            </p>
          </div>

          <div v-show="syntaxTab === 'css'">
            <div class="mb-4 overflow-hidden rounded-sm border border-sidebar-accent">
              <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
                <span
                  class="font-mono text-xs text-muted-foreground">overlay.css — live values can be used inside CSS</span>
              </div>
              <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
                <div><span class="text-sky-600 dark:text-sky-400">.follower-bar</span><span class="text-zinc-500"> &#123;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-600 dark:text-zinc-400">width</span><span class="text-zinc-500">: calc(</span
                ><span class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span><span
                  class="text-zinc-500"> / </span
                ><span class="text-amber-700 dark:text-amber-400">[[[goals_latest_target]]]</span><span
                  class="text-zinc-500"> * 100%);</span>
                </div>
                <div><span class="text-zinc-500">&#125;</span></div>
                <div class="mt-3"></div>
                <div><span class="text-sky-600 dark:text-sky-400">.stream-title::before</span><span
                  class="text-zinc-500"> &#123;</span></div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-600 dark:text-zinc-400">content</span><span
                  class="text-zinc-500">: </span><span class="text-emerald-600 dark:text-emerald-400">"</span
                ><span class="text-amber-700 dark:text-amber-400">[[[channel_title]]]</span><span
                  class="text-emerald-600 dark:text-emerald-400">"</span><span class="text-zinc-500">;</span>
                </div>
                <div><span class="text-zinc-500">&#125;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Dynamic widths, generated content, colour values driven by data — anything a CSS value can express, a tag
              can provide.
            </p>
          </div>

          <div v-show="syntaxTab === 'events'">
            <div class="mb-4 overflow-hidden rounded-sm border border-sidebar-accent">
              <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
                <span class="font-mono text-xs text-muted-foreground">Alert template — channel.follow</span>
              </div>
              <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
                <div>
                  <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"follow-alert"</span
                ><span class="text-zinc-500">&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;h1&gt;</span><span
                  class="text-amber-700 dark:text-amber-400">[[[event.user_name]]]</span
                ><span class="text-foreground"> just followed!</span><span class="text-zinc-500">&lt;/h1&gt;</span>
                </div>
                <div>
                  &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span
                  class="text-foreground">Follower #</span><span class="text-amber-700 dark:text-amber-400">[[[followers_total]]]</span
                ><span class="text-zinc-500">&lt;/p&gt;</span>
                </div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2 text-xs text-muted-foreground/60">
                  <span class="text-zinc-500">&lt;!--</span> First ever: wilko_dj <span
                  class="text-zinc-500">--&gt;</span>
                </div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Event tags are merged with your static overlay data at render time. All static tags remain available
              inside alert templates. You're encouraged to mix them freely.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- 02 — Controls -->
    <section id="controls" class="scroll-mt-16 border-b border-sidebar-accent bg-sidebar-accent py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Controls</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Typed, mutable overlay state.</h2>
          <p class="mb-3 max-w-2xl text-lg text-foreground">
            Controls are named, typed values you define per template and update from your dashboard while the overlay is
            live in OBS. Change a value
            and your overlay re-renders the new data near-instantly. All without page reloads, of course!
          </p>
          <p class="mb-12 max-w-2xl text-foreground">
            Reference any control with <code
            class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">[[[c:key]]]</code>
            — in HTML,
            in CSS, and in conditional blocks.
            <Link href="/help/controls" class="ml-1 text-sky-500 hover:underline">Full controls reference →</Link>
          </p>

          <!-- Control types grid -->
          <div class="mb-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="ctrl in controlTypes" :key="ctrl.type"
                 class="rounded-sm bg-card p-4">
              <div class="mb-2 flex items-center gap-2">
                <component :is="ctrl.icon" class="h-4 w-4 shrink-0 text-sky-500" />
                <span class="font-mono text-sm font-semibold">{{ ctrl.type }}</span>
              </div>
              <p class="mb-3 text-sm text-muted-foreground">{{ ctrl.description }}</p>
              <div
                class="overflow-x-auto rounded bg-accent px-3 py-1.5 font-mono text-xs text-amber-700 dark:text-amber-400">
                {{ ctrl.example }}
              </div>
            </div>
          </div>

          <!-- Power combo -->
          <div class="overflow-hidden rounded-sm ">
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
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span
                class="text-emerald-600 dark:text-emerald-400">"timer </span
              ><span class="text-sky-600 dark:text-sky-400">[[[if:c:round_timer &lt;= 10]]]</span><span
                class="text-emerald-600 dark:text-emerald-400">danger</span
              ><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span><span
                class="text-emerald-600 dark:text-emerald-400">"</span><span class="text-zinc-500">&gt;</span>
              </div>
              <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">[[[c:round_timer]]]</span>
              </div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>
          <p class="mt-3 text-sm text-muted-foreground">
            The timer ticks at 250ms resolution. The <code
            class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-amber-700 dark:text-amber-400">danger</code>
            class applies
            automatically when the countdown reaches 10 seconds. Flip the boolean from the dashboard to show or hide the
            block, with near-live updates.
          </p>
        </div>
      </div>
    </section>

    <!-- 03 — Conditionals -->
    <section id="conditionals" class="scroll-mt-16 border-b border-sidebar-accent py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Conditional Rendering</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">A comparison engine in your template.</h2>
          <p class="mb-12 max-w-2xl text-lg text-foreground">
            Any tag (Twitch data, control value, or event payload) can drive a conditional block. Evaluated
            client-side in the overlay with no
            server round-trips and no <code class="rounded bg-muted px-1.5 text-sm">eval()</code>. Nesting supported up
            to 10 levels deep.
            <Link href="/help/conditionals" class="ml-1 text-sky-500 hover:underline">Full syntax reference →</Link>
          </p>

          <div class="mb-10 grid gap-8 lg:grid-cols-2">
            <div>
              <h3 class="mb-4 text-xs font-semibold tracking-widest text-foreground uppercase">Syntax</h3>
              <div class="overflow-hidden rounded-sm">
                <div class="bg-card p-5 font-mono text-sm leading-7">
                  <div><span class="text-sky-600 dark:text-sky-400">[[[if:variable operator value]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-600 dark:text-sky-400">[[[elseif:variable operator value]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-600 dark:text-sky-400">[[[else]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-4 text-xs font-semibold tracking-widest text-foreground uppercase">Operators</h3>
              <div class="divide-y divide-sidebar-accent overflow-hidden rounded-sm bg-card/50">
                <div
                  v-for="[op, label] in [
                    ['=', 'Equal'],
                    ['!=', 'Not equal'],
                    ['>', 'Greater than'],
                    ['<', 'Less than'],
                    ['>=', 'Greater than or equal'],
                    ['<=', 'Less than or equal'],
                  ]"
                  :key="op"
                  class="flex items-center gap-4 bg-card/50 px-4 py-2.5"
                >
                  <code class="w-8 shrink-0 font-mono text-sm text-amber-700 dark:text-amber-400">{{ op }}</code>
                  <span class="text-sm text-muted-foreground">{{ label }}</span>
                </div>
              </div>
              <p class="mt-3 text-xs text-muted-foreground">
                Numeric comparisons are numeric. Truthy check treats <code class="rounded bg-muted px-1">"0"</code>,
                <code class="rounded bg-muted px-1">"false"</code>, and empty string as false.
              </p>
            </div>
          </div>

          <div class="overflow-hidden rounded-sm">
            <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">Language-aware overlay + milestone block</span>
            </div>
            <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
              <div><span class="text-sky-600 dark:text-sky-400">[[[if:channel_language = en]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Welcome to the stream</span
              ><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[elseif:channel_language = es]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Bienvenidos al stream</span
              ><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[else]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-foreground">Welcome</span
              ><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
              <div class="mt-3"></div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[if:followers_total &gt;= 10000]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span
                class="text-emerald-600 dark:text-emerald-400">"tenk-badge"</span
              ><span class="text-zinc-500">&gt;</span><span class="text-foreground">10K club</span><span
                class="text-zinc-500">&lt;/div&gt;</span>
              </div>
              <div><span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 04 — Events -->
    <section id="events" class="scroll-mt-16 border-b border-sidebar-accent bg-sidebar-accent py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Event Alerts</Badge>
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
            <div v-for="evt in twitchEvents" :key="evt.type"
                 class="rounded-sm border border-sidebar-accent bg-card p-4 w-full">
              <div class="mb-1 text-sm font-semibold">{{ evt.label }}</div>
              <div class="mb-3 max-w-full overflow-x-hidden font-mono text-xs text-muted-foreground">{{ evt.type }}
              </div>
              <div v-if="evt.tag"
                   class="rounded bg-accent px-2.5 py-1.5 font-mono text-xs text-amber-700 dark:text-amber-300">
                [[[{{ evt.tag }}]]]
              </div>
              <div v-else class="rounded bg-sidebar-accent px-2.5 py-1.5 font-mono text-xs text-zinc-600">no payload</div>
            </div>
          </div>

          <!-- Alert pipeline -->
          <div class="overflow-hidden rounded-sm">
            <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">What happens when a raid fires</span>
            </div>
            <div class="divide-y divide-border/50">
              <div
                v-for="(step, i) in alertPipelineSteps"
                :key="i"
                class="flex items-start gap-4 bg-card px-5 py-3.5 transition-colors hover:bg-muted/30"
              >
                <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500/70">{{ String(i + 1).padStart(2, '0')
                  }}</span>
                <span class="text-sm text-foreground">{{ step }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 05 — Integrations -->
    <section id="integrations" class="scroll-mt-16 border-b border-sidebar-accent py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <div class="mb-4 flex items-center gap-3">
            <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Integrations</Badge>
            <Badge class="border-emerald-500/40 bg-emerald-500/10 hover:border-emerald-500/40 hover:bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-500">
              Now supports StreamElements!
            </Badge>
          </div>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Show donations from different sources.</h2>
          <p class="mb-12 max-w-2xl text-lg text-foreground">
            Connect your Ko-fi, StreamElements or Streamlabs account and Overlabels automatically tracks every donation in real time.
            Counters update, alerts fire, and your overlay stays current - all without touching a single line of code
            after setup.
          </p>

          <!-- Integration tabs -->
          <div class="mb-8 flex gap-0 overflow-x-auto border-b border-sidebar-accent">
            <button
              v-for="service in (['kofi', 'streamlabs', 'streamelements'] as const)"
              :key="service"
              @click="integrationsTab = service"
              :class="[
                '-mb-px shrink-0 cursor-pointer border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                integrationsTab === service ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground',
              ]"
            >
              {{ integrationConfigs[service].name }}
            </button>
          </div>

          <!-- Unified integration card -->
          <div class="mb-12 rounded-sm bg-card p-6">
            <div class="mb-4 flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-500/10">
                <Heart v-if="integrationsTab === 'kofi'" class="h-5 w-5 text-sky-500" />
                <DollarSign v-else class="h-5 w-5 text-sky-500" />
              </div>
              <div>
                <h3 class="font-semibold">{{ integrationConfigs[integrationsTab].name }}</h3>
                <p class="text-xs text-muted-foreground">{{ integrationConfigs[integrationsTab].tagline }}</p>
              </div>
            </div>
            <p class="mb-4 max-w-3xl text-sm text-foreground">
              {{ integrationConfigs[integrationsTab].description }}
            </p>
            <p class="mb-4 text-xs text-muted-foreground">
              Six auto-provisioned controls, identical shape across every service:
            </p>
            <div class="grid gap-1.5 font-mono text-xs sm:grid-cols-2">
              <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
                [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:total_received]]]
              </div>
              <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
                [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donor_name]]]
              </div>
              <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
                [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:donations_received]]]
              </div>
              <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
                [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donation_amount]]]
              </div>
              <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
                [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donation_message]]]
              </div>
              <div class="rounded bg-accent px-2.5 py-1.5 text-amber-700 dark:text-amber-400">
                [[[c:<span class="text-sky-500">{{ integrationConfigs[integrationsTab].namespace }}</span>:latest_donation_currency]]]
              </div>
            </div>
          </div>

          <!-- Shared alert template example -->
          <div class="overflow-hidden rounded-sm">
            <div class="border-b border-sidebar-accent bg-card/50 px-4 py-2.5">
              <span class="font-mono text-xs text-muted-foreground">One alert template works for all connected external donation services</span>
            </div>
            <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
              <div>
                <span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-600 dark:text-emerald-400">"donation-alert"</span><span
                class="text-zinc-500">&gt;</span>
              </div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;h2&gt;</span><span
                class="text-amber-700 dark:text-amber-400">[[[event.from_name]]]</span><span class="text-foreground"> donated </span><span
                class="text-amber-700 dark:text-amber-400">[[[event.formatted_amount]]]</span><span
                class="text-zinc-500">&lt;/h2&gt;</span>
              </div>
              <div>&nbsp;&nbsp;<span class="text-sky-600 dark:text-sky-400">[[[if:event.message]]]</span></div>
              <div>
                &nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span
                class="text-amber-700 dark:text-amber-400">[[[event.message]]]</span><span class="text-zinc-500">&lt;/p&gt;</span>
              </div>
              <div>&nbsp;&nbsp;<span class="text-sky-600 dark:text-sky-400">[[[endif]]]</span></div>
              <div>
                &nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span
                class="text-foreground">via </span><span
                class="text-amber-700 dark:text-amber-400">[[[event.source]]]</span><span class="text-zinc-500">&lt;/small&gt;</span>
              </div>
              <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
            </div>
          </div>
          <p class="mt-3 text-sm text-muted-foreground">
            Ko-fi, StreamElements and Streamlabs expose the same normalized event tags. Write your donation alert once and it works for
            both - <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-amber-700 dark:text-amber-400">[[[event.source]]]</code>
            tells your overlay which platform it came from.
          </p>

          <!-- The latest() killer feature -->
          <div class="mt-16 border-t border-sidebar-accent pt-16">
            <Badge class="mb-4 border-sky-500/40 bg-sky-500/10 hover:border-sky-500/40 hover:bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold text-sky-500">
              No vendor lock-in
            </Badge>
            <h3 class="mb-4 text-2xl font-bold sm:text-3xl">
              Three donation services plus Twitch bits. One <code class="font-mono text-sky-500">latest()</code>.
            </h3>
            <p class="mb-4 max-w-3xl text-lg text-foreground">
              Every other overlay tool on the market is owned by a donation platform. Streamlabs' overlays show Streamlabs donations. StreamElements' overlays show StreamElements donations. Ko-fi's overlays show Ko-fi donations. That's not a bug, it's the business model.
            </p>
            <p class="mb-8 max-w-3xl text-lg text-foreground">
              Overlabels doesn't sell donation ingest, so we don't care which service the money came through. Pass all three donation services plus Twitch bits into a single <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-base text-sky-500">latest()</code> and you get the actual most-recent supporter across your whole stream. <strong>One name, one amount, four pipes.</strong>
            </p>

            <div class="overflow-hidden rounded-sm">
              <div class="flex items-center gap-2 border-b border-sky-500/20 bg-sky-400/10 dark:bg-sky-950/20 px-4 py-2.5">
                <span class="font-mono text-xs text-sky-600 dark:text-sky-400">Two expression controls. The whole cross-service story.</span>
              </div>
              <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
                <div><span class="text-zinc-600 dark:text-zinc-400 italic">// c:latest_donator</span></div>
                <div><span class="text-sky-600 dark:text-sky-400">latest</span><span class="text-zinc-500">(</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.streamlabs.latest_donor_name_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamlabs.latest_donor_name</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.kofi.latest_donor_name_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.kofi.latest_donor_name</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.streamelements.latest_donor_name_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamelements.latest_donor_name</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.twitch.latest_cheerer_name_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.twitch.latest_cheerer_name</span></div>
                <div><span class="text-zinc-500">)</span></div>
                <div class="mt-4"></div>
                <div><span class="text-zinc-600 dark:text-zinc-400 italic">// c:last_donation_amount</span></div>
                <div><span class="text-sky-600 dark:text-sky-400">latest</span><span class="text-zinc-500">(</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.kofi.latest_donation_amount_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.kofi.latest_donation_amount</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.streamelements.latest_donation_amount_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamelements.latest_donation_amount</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.streamlabs.latest_donation_amount_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.streamlabs.latest_donation_amount</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.twitch.latest_cheer_amount_at</span><span class="text-zinc-500">,</span> <span class="text-amber-700 dark:text-amber-400">c.twitch.latest_cheer_amount</span></div>
                <div><span class="text-zinc-500">)</span></div>
              </div>
            </div>
            <p class="mt-3 max-w-3xl text-sm text-muted-foreground">
              <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-sky-500">latest()</code> takes pairs of <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">(timestamp, label)</code> arguments, picks the highest timestamp, and returns its paired label. Every control in Overlabels automatically exposes an <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-amber-700 dark:text-amber-400">_at</code> companion holding its last-update time in seconds - every timestamp on the platform is normalized that way - so the same pattern works for totals, counters, or anything else you want to rank by recency. Reactive, so your overlay catches up the instant a new donation lands on any pipe.
            </p>
          </div>

          <!-- Reverse subathon case study -->
          <div class="mt-16 border-t border-sidebar-accent pt-16">
            <Badge class="mb-4 border-sky-500/40 bg-sky-500/10 hover:border-sky-500/40 hover:bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold text-sky-500">
              Case study
            </Badge>
            <h3 class="mb-4 text-2xl font-bold sm:text-3xl">
              Here's how to do a <span class="text-sky-500">reverse subathon</span>.
            </h3>
            <p class="mb-4 max-w-3xl text-lg text-foreground">
              A reverse subathon is the evil twin of the classic. The clock starts at some big number and every donation <strong>subtracts</strong> time. When it hits zero, the stream ends. It is the chaos engine of audience-participation streaming, and it's three controls and one expression in Overlabels.
            </p>
            <p class="mb-8 max-w-3xl text-lg text-foreground">
              Create three number controls: <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">c.donathon_timer</code> (starting seconds), <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">c.deduction_per_donation</code> (seconds to strip per donation), and <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 font-mono text-sm text-amber-700 dark:text-amber-400">c.total_donations</code> (a counter your alert template increments on every donation across every service). Then a single expression control does the rest:
            </p>

            <div class="overflow-hidden rounded-sm">
              <div class="flex items-center gap-2 border-b border-sky-500/20 bg-sky-400/10 dark:bg-sky-950/20 px-4 py-2.5">
                <span class="font-mono text-xs text-sky-600 dark:text-sky-400">One expression. The whole show.</span>
              </div>
              <div class="overflow-x-auto bg-card p-5 font-mono text-sm leading-7">
                <div><span class="text-zinc-600 dark:text-zinc-400 italic">// c:time_remaining</span></div>
                <div><span class="text-sky-600 dark:text-sky-400">clamp</span><span class="text-zinc-500">(</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.donathon_timer</span> <span class="text-zinc-500">-</span> <span class="text-zinc-500">(</span><span class="text-amber-700 dark:text-amber-400">c.deduction_per_donation</span> <span class="text-zinc-500">*</span> <span class="text-amber-700 dark:text-amber-400">c.total_donations</span><span class="text-zinc-500">),</span></div>
                <div>&nbsp;&nbsp;<span class="text-emerald-600 dark:text-emerald-400">0</span><span class="text-zinc-500">,</span></div>
                <div>&nbsp;&nbsp;<span class="text-amber-700 dark:text-amber-400">c.donathon_timer</span></div>
                <div><span class="text-zinc-500">)</span></div>
              </div>
            </div>
            <p class="mt-3 max-w-3xl text-sm text-muted-foreground">
              <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs text-sky-500">clamp()</code> keeps the result between zero and the original timer so the clock can't go negative or somehow inflate. Pipe it through <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">|duration:hh:mm:ss</code> and you have a broadcast-ready countdown that reacts the instant any donation lands on any service.
            </p>

            <div class="mt-8 max-w-3xl border-l-4 border-sky-500/40 bg-sky-500/5 px-5 py-4">
              <p class="text-sm text-foreground">
                <strong>Btw</strong>: if you want a classic subathon that <em>adds</em> time on every donation, just swap the <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">-</code> for a <code class="rounded bg-zinc-100 dark:bg-zinc-900 px-1 text-xs">+</code>. That's it. You're welcome &lt;3
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 06 — Kits & Copying -->
    <section id="kits" class="scroll-mt-16 border-b bg-card py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Kits &amp; Copying</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Good design compounds.</h2>

          <div class="flex items-center justify-center mb-4 max-w-3xl gap-2.5 border border-violet-500/30 bg-violet-500/10 px-4 py-3">
            <AlertTriangle class="h-4 w-4 shrink-0 text-violet-500" />
            <p class="text-sm text-violet-700 dark:text-violet-300">
              <strong>Work in progress</strong>. Duplicating overlays works, but Integration-controlled Controls may not transfer correctly.
            </p>
          </div>
          <p class="mb-12 max-w-3xl text-lg text-foreground">
            Any public template or kit can be copied. One click, one copy, fully yours to modify, extend, or break. An
            Overlay Kit is a collection of
            templates - a static overlay, a follower alert, a subscription alert, a raid alert - designed as a cohesive
            visual system. Copy the kit,
            get everything at once.
          </p>

          <div class="grid gap-6 sm:grid-cols-3">
            <div class="rounded-sm border border-sidebar-accent bg-sidebar-accent p-6">
              <GitFork class="mb-4 h-8 w-8 text-sky-500" />
              <h3 class="mb-2 font-semibold">Copy anything public</h3>
              <p class="text-sm text-muted-foreground">
                Every public template is a starting point. Copy it, own it, ship it. The original is always untouched.
              </p>
            </div>
            <div class="rounded-sm border border-sidebar-accent bg-sidebar-accent p-6">
              <Layers class="mb-4 h-8 w-8 text-sky-500" />
              <h3 class="mb-2 font-semibold">Overlay Kits</h3>
              <p class="text-sm text-muted-foreground">
                Collections of templates sharing a visual language. Copy the kit, get the whole system. No assembly
                required.
              </p>
            </div>
            <div class="rounded-sm border border-sidebar-accent bg-sidebar-accent p-6">
              <Shield class="mb-4 h-8 w-8 text-sky-500" />
              <h3 class="mb-2 font-semibold">Controls carry over</h3>
              <p class="text-sm text-muted-foreground">
                Copying a template with controls opens the Import Wizard. Pick which controls come with the copy.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 07 — Zero to Overlay -->
    <section class="border-b border-sidebar-accent py-24">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <Badge variant="default" class="mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Getting started</Badge>
          <h2 class="mb-4 text-3xl font-bold sm:text-4xl">The Onboarding Wizard</h2>
          <p class="mb-12 max-w-2xl text-lg text-foreground">
            After signing up, the system will trigger an onboarding wizard which will set you up with the defaults you need to
            make Overlabels work for you: One overlay, a bunch of alerts and your secret token is generated and applied to the URL
            you need to add to your OBS. We also generate your personal template tags that match the level of your Twitch account.
            This so you don't up with affiliate level capabilities if you're a Twitch partner and vice versa. Yeah, we're cool like that.
          </p>

          <div class="grid gap-10 sm:grid-cols-2">
            <div>
              <h3
                class="mb-5 flex items-center gap-2 text-sm font-semibold tracking-widest text-foreground uppercase">
                <Zap class="h-4 w-4 text-sky-500" />
                Automated on signup
              </h3>
              <ul class="space-y-4 text-sm">
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">01</span>
                  <span
                    class="text-foreground">Secure webhook connection configured</span>
                </li>
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">02</span>
                  <span class="text-foreground">Starter kit copied into your account</span>
                </li>
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">03</span>
                  <span
                    class="text-foreground">Alerts mapped to events automatically</span>
                </li>
                <li class="flex items-start gap-3">
                  <span class="mt-0.5 shrink-0 font-mono text-xs text-sky-500">04</span>
                  <span class="text-foreground">Tag set generated from your Twitch data</span>
                </li>
              </ul>
            </div>

            <div>
              <h3
                class="mb-5 flex items-center gap-2 text-sm font-semibold tracking-widest text-foreground uppercase">
                <Code2 class="h-4 w-4 text-sky-500" />
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
                  <span class="text-zinc-500">&nbsp;&nbsp;-F </span><span
                  class="text-emerald-600 dark:text-emerald-400">https://overlabels.com/api/twitch/webhook</span
                ><span class="text-zinc-500"> \</span>
                </div>
                <div>
                  <span class="text-zinc-500">&nbsp;&nbsp;-s </span><span class="text-amber-700 dark:text-amber-300">your_webhook_secret</span
                ><span class="text-zinc-500"> \</span>
                </div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;--to-user </span><span
                  class="text-amber-700 dark:text-amber-300">your_twitch_id</span></div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;--from-user </span><span
                  class="text-amber-700 dark:text-amber-300">another_twitch_id</span></div>
              </div>
              <p class="mt-3 text-xs text-foreground">You'll need to have
                <a href="https://dev.twitch.tv/docs/cli/"
                   class="text-sky-500 hover:text-sky-500 hover:underline" target="_blank">Twitch CLI</a> installed for this to work.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
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

          <div v-if="$page.props.auth.user" class="flex flex-col items-center gap-4">
            <Link :href="route('dashboard.index')" class="btn btn-primary px-8 text-base"> Go to Dashboard
              <ArrowRight class="ml-2 h-5 w-5" />
            </Link>
          </div>
          <div v-else class="flex flex-col items-center gap-6">
            <LoginSocial />
            <p class="text-xs text-foreground">
              Authenticate with Twitch. You must have an email address attached to your account before you can login.
              Revoke access anytime from your Twitch settings.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-12">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col items-center justify-between gap-4 sm:flex-row">
          <div class="flex items-center gap-2.5">
            <img src="/favicon-light.svg" alt="" class="h-8 w-8 dark:hidden" /><img src="/favicon.png" alt=""
                                                                                    class="hidden h-8 w-8 dark:block" />
            <span class="font-semibold">Overlabels</span>
            <Badge variant="outline" class="text-xs">Beta</Badge>
          </div>
          <div class="flex flex-wrap items-center gap-6 text-sm text-foreground">
            <Link href="/help" class="hover:text-sky-500">Help</Link>
            <Link href="/help/controls" class="hover:text-sky-500">Controls</Link>
            <Link href="/help/manifesto" class="hover:text-sky-500">Why Overlabels</Link>
            <Link href="/terms" class="hover:text-sky-500">Terms</Link>
            <Link href="/privacy" class="hover:text-sky-500">Privacy</Link>
            <a
              href="https://github.com/jasperfrontend/overlabels"
              target="_blank"
              rel="noopener"
              class="group flex items-center gap-1.5 hover:text-sky-500"
            >
              GitHub
              <svg role="img" viewBox="0 0 24 24" class="size-4 fill-current" xmlns="http://www.w3.org/2000/svg"><title>GitHub</title><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
            </a>
          </div>
        </div>
        <div class="border-t border-sidebar-accent pt-8 text-center flex flex-col gap-1 text-xs">
          <p>Made by <a href="https://twitch.tv/JasperDiscovers" class="text-sky-500 hover:underline" target="_blank">JasperDiscovers</a> for the Twitch streaming community.</p>
          <p><strong>FAQ</strong>: Will you support Kick.com? <strong>No</strong>.</p>
        </div>
      </div>
    </footer>
  </div>
</template>
