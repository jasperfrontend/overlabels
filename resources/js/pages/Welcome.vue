<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
  ArrowRight,
  Github,
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
} from 'lucide-vue-next';
import { ref } from 'vue';

const syntaxTab = ref('static');

const controlTypes = [
  {
    type: 'text',
    icon: Type,
    description: 'Strings up to 1000 chars. HTML stripped on save.',
    example: '[[[c:myname]]] → JasperDiscovers',
  },
  {
    type: 'number',
    icon: Hash,
    description: 'Numeric values. Safely coerced, defaults to 0.',
    example: '[[[c:goal_target]]] → 500',
  },
  {
    type: 'counter',
    icon: SlidersHorizontal,
    description: 'Integer counter. Increment or decrement from the dashboard.',
    example: '[[[c:kill_count]]] → 14',
  },
  {
    type: 'timer',
    icon: Timer,
    description: 'Countup or countdown at 250ms resolution. State broadcast over WebSocket.',
    example: '[[[c:round_timer]]] → 4:32',
  },
  {
    type: 'datetime',
    icon: Calendar,
    description: 'ISO 8601 datetime. For scheduled events, stream start times.',
    example: '[[[c:next_event]]] → 2026-03-01T20:00:00Z',
  },
  {
    type: 'boolean',
    icon: ToggleLeft,
    description: 'Stores "1" or "0". Toggle overlay sections live from your dashboard.',
    example: '[[[if:c:show_goal]]] → show or hide',
  },
];

const twitchEvents = [
  { type: 'channel.follow', label: 'New Follower', tag: 'event.user_name' },
  { type: 'channel.subscribe', label: 'New Subscription', tag: 'event.tier' },
  { type: 'channel.subscription.gift', label: 'Gift Subscriptions', tag: 'event.total' },
  { type: 'channel.subscription.message', label: 'Resubscription', tag: 'event.message.text' },
  { type: 'channel.cheer', label: 'Bits Cheer', tag: 'event.bits' },
  { type: 'channel.raid', label: 'Incoming Raid', tag: 'event.viewers' },
  { type: 'channel.channel_points_custom_reward_redemption.add', label: 'Channel Points', tag: 'event.reward.title' },
  { type: 'stream.online', label: 'Stream Online', tag: 'event.type' },
  { type: 'stream.offline', label: 'Stream Offline', tag: '—' },
];

const alertPipelineSteps = [
  'Twitch sends a webhook POST to /api/twitch/webhook',
  'HMAC-SHA256 signature validated against your per-user webhook secret',
  'Mapping lookup finds the template assigned to the event type for your account',
  'Current overlay data merged with the event payload (event.viewers, event.user_name, etc.)',
  'Compiled alert broadcast to Pusher channel alerts.{twitch_id}',
  'Overlay receives the payload, renders into the alert DOM node, plays transition',
  'Auto-dismisses after configured duration. Static overlay continues uninterrupted.',
];
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <Head>
      <title>Overlabels — A live overlay DSL for Twitch</title>
      <meta name="description" content="Write HTML and CSS. Bind live Twitch data with triple-bracket tags. React to every Twitch event. Overlabels is a declarative, state-driven overlay engine for OBS." />
    </Head>

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 backdrop-blur-lg bg-background/80 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center gap-2.5">
            <img src="/favicon.png" alt="" class="w-8 h-8" />
            <span class="text-lg font-bold tracking-tight">Overlabels</span>
            <Badge variant="outline" class="text-xs">Beta</Badge>
          </div>
          <div class="flex items-center gap-6">
            <Link href="/help" class="hidden sm:block text-sm text-muted-foreground hover:text-foreground transition-colors">Docs</Link>
            <Link href="/manifesto" class="hidden sm:block text-sm text-muted-foreground hover:text-foreground transition-colors">Manifesto</Link>
            <DarkModeToggle />
            <Link v-if="$page.props.auth.user" :href="route('dashboard.index')" class="btn btn-primary text-sm">
              Dashboard <ArrowRight class="ml-1.5 h-4 w-4" />
            </Link>
            <LoginSocial v-else />
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="py-24 sm:py-36 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <div class="mb-6 flex flex-wrap items-center gap-3">
            <Badge variant="outline" class="text-xs font-mono px-3 py-1 border-sky-500/40 text-sky-500">
              DSL · State-driven · Deterministic
            </Badge>
            <Badge variant="outline" class="text-xs px-3 py-1">Free forever · Open source</Badge>
          </div>

          <h1 class="text-5xl sm:text-6xl md:text-7xl font-bold tracking-tight mb-6 leading-[1.05]">
            A live overlay DSL<br />
            <span class="text-sky-500">for Twitch.</span>
          </h1>

          <p class="text-xl text-muted-foreground max-w-2xl mb-4 leading-relaxed">
            Write HTML and CSS. Bind live Twitch data with a triple-bracket tag syntax.
            React to every Twitch event. Mutate overlay state from your dashboard in real time.
          </p>
          <p class="text-base text-muted-foreground max-w-2xl mb-14">
            No drag-and-drop. No proprietary format. No lock-in.
            Your overlay is a webpage — Overlabels is the engine that keeps it alive.
          </p>

          <!-- Hero code blocks -->
          <div class="grid lg:grid-cols-2 gap-4 mb-14">
            <div class="rounded-md border border-border overflow-hidden">
              <div class="flex items-center gap-2 px-4 py-2.5 bg-muted/50 border-b border-border">
                <span class="text-xs text-muted-foreground font-mono">overlay.html</span>
              </div>
              <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
                <div><span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"stat-bar"</span><span class="text-zinc-500">&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-400">[[[followers_total]]]</span><span class="text-zinc-500">&lt;/span&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span class="text-zinc-300">followers</span><span class="text-zinc-500">&lt;/small&gt;</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2"></div>
                <div><span class="text-sky-400">[[[if:followers_total &gt;= 1000]]]</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"milestone"</span><span class="text-zinc-500">&gt;</span></div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-300">four digits. let's go.</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div><span class="text-sky-400">[[[endif]]]</span></div>
              </div>
            </div>

            <div class="rounded-md border border-emerald-500/30 overflow-hidden">
              <div class="flex items-center gap-2 px-4 py-2.5 bg-emerald-950/30 border-b border-emerald-500/20">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs text-emerald-400 font-mono">live in OBS</span>
              </div>
              <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
                <div><span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"stat-bar"</span><span class="text-zinc-500">&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-emerald-300">1,342</span><span class="text-zinc-500">&lt;/span&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;small&gt;</span><span class="text-zinc-300">followers</span><span class="text-zinc-500">&lt;/small&gt;</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div class="mt-2"></div>
                <div><span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"milestone"</span><span class="text-zinc-500">&gt;</span></div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-300">four digits. let's go.</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-4">
            <Link v-if="!$page.props.auth.user" href="#get-started">
              <button class="btn btn-primary">Get started free <ArrowRight class="ml-2 h-4 w-4" /></button>
            </Link>
            <Link v-else :href="route('dashboard.index')">
              <button class="btn btn-primary">Go to dashboard <ArrowRight class="ml-2 h-4 w-4" /></button>
            </Link>
            <Link href="/manifesto">
              <button class="btn btn-secondary">Read the manifesto</button>
            </Link>
          </div>
        </div>
      </div>
    </section>

    <!-- 01 — The Syntax -->
    <section class="py-24 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <Badge variant="outline" class="text-xs font-mono px-3 py-1 mb-4">01 — The Syntax</Badge>
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Triple brackets. That's it.</h2>
          <p class="text-muted-foreground max-w-2xl mb-12 text-lg">
            The syntax is deliberately collision-resistant — too distinctive to ever conflict with HTML, CSS, or any template engine, and simple enough to read without a tutorial.
            Tags resolve to live Twitch data. They work in HTML, in CSS, and inside conditional blocks.
          </p>

          <!-- Tabs -->
          <div class="flex gap-0 border-b border-border mb-8">
            <button
              @click="syntaxTab = 'static'"
              :class="['px-4 py-2.5 text-sm font-medium transition-colors cursor-pointer border-b-2 -mb-px', syntaxTab === 'static' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground']"
            >Static data</button>
            <button
              @click="syntaxTab = 'css'"
              :class="['px-4 py-2.5 text-sm font-medium transition-colors cursor-pointer border-b-2 -mb-px', syntaxTab === 'css' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground']"
            >In CSS</button>
            <button
              @click="syntaxTab = 'events'"
              :class="['px-4 py-2.5 text-sm font-medium transition-colors cursor-pointer border-b-2 -mb-px', syntaxTab === 'events' ? 'border-sky-500 text-sky-500' : 'border-transparent text-muted-foreground hover:text-foreground']"
            >Event alerts</button>
          </div>

          <div v-show="syntaxTab === 'static'">
            <div class="rounded-md border border-border overflow-hidden mb-4">
              <div class="px-4 py-2.5 bg-muted/50 border-b border-border">
                <span class="text-xs text-muted-foreground font-mono">Static overlay — subscriber bar</span>
              </div>
              <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
                <div><span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"sub-bar"</span><span class="text-zinc-500">&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-400">[[[subscribers_total]]]</span><span class="text-zinc-300"> subs</span><span class="text-zinc-500">&lt;/span&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-zinc-300">Latest: </span><span class="text-amber-400">[[[subscribers_latest_user_name]]]</span><span class="text-zinc-500">&lt;/span&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;span&gt;</span><span class="text-amber-400">[[[channel_game]]]</span><span class="text-zinc-300"> — </span><span class="text-amber-400">[[[channel_title]]]</span><span class="text-zinc-500">&lt;/span&gt;</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              Available tags span user data, channel info, followers, subscribers, goals, and more.
              <Link href="/help" class="text-sky-500 hover:underline">Browse all template tags →</Link>
            </p>
          </div>

          <div v-show="syntaxTab === 'css'">
            <div class="rounded-md border border-border overflow-hidden mb-4">
              <div class="px-4 py-2.5 bg-muted/50 border-b border-border">
                <span class="text-xs text-muted-foreground font-mono">overlay.css — tags resolve before CSS injects into the document head</span>
              </div>
              <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
                <div><span class="text-sky-400">.follower-bar</span><span class="text-zinc-500"> &#123;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-400">width</span><span class="text-zinc-500">: calc(</span><span class="text-amber-400">[[[followers_total]]]</span><span class="text-zinc-500"> / </span><span class="text-amber-400">[[[goals_latest_target]]]</span><span class="text-zinc-500"> * 100%);</span></div>
                <div><span class="text-zinc-500">&#125;</span></div>
                <div class="mt-3"></div>
                <div><span class="text-sky-400">.stream-title::before</span><span class="text-zinc-500"> &#123;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-400">content</span><span class="text-zinc-500">: </span><span class="text-emerald-400">"</span><span class="text-amber-400">[[[channel_title]]]</span><span class="text-emerald-400">"</span><span class="text-zinc-500">;</span></div>
                <div><span class="text-zinc-500">&#125;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">Dynamic widths, generated content, colour values driven by data — anything a CSS value can express, a tag can provide.</p>
          </div>

          <div v-show="syntaxTab === 'events'">
            <div class="rounded-md border border-border overflow-hidden mb-4">
              <div class="px-4 py-2.5 bg-muted/50 border-b border-border">
                <span class="text-xs text-muted-foreground font-mono">Alert template — channel.cheer</span>
              </div>
              <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
                <div><span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"cheer-alert"</span><span class="text-zinc-500">&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;h1&gt;</span><span class="text-amber-400">[[[event.user_name]]]</span><span class="text-zinc-300"> cheered!</span><span class="text-zinc-500">&lt;/h1&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-amber-400">[[[event.bits]]]</span><span class="text-zinc-300"> bits — total subs: </span><span class="text-amber-400">[[[subscribers_total]]]</span><span class="text-zinc-500">&lt;/p&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-sky-400">[[[if:event.bits &gt;= 1000]]]</span></div>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"whale"</span><span class="text-zinc-500">&gt;</span><span class="text-zinc-300">big cheer</span><span class="text-zinc-500">&lt;/div&gt;</span></div>
                <div>&nbsp;&nbsp;<span class="text-sky-400">[[[endif]]]</span></div>
                <div><span class="text-zinc-500">&lt;/div&gt;</span></div>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">Event tags are merged with your static overlay data at render time. All static tags remain available inside alert templates — mix them freely.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 02 — Controls -->
    <section class="py-24 bg-muted/20 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <Badge variant="outline" class="text-xs font-mono px-3 py-1 mb-4">02 — Controls</Badge>
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Typed, mutable overlay state.</h2>
          <p class="text-muted-foreground max-w-2xl mb-3 text-lg">
            Controls are named, typed values you define per template and update from your dashboard while the overlay is live in OBS. Change a value and every connected overlay re-renders that tag instantly — no page reload, no OBS interaction required.
          </p>
          <p class="text-muted-foreground max-w-2xl mb-12">
            Reference any control with <code class="bg-zinc-900 text-amber-400 px-1.5 py-0.5 rounded text-sm font-mono">[[[c:key]]]</code> — in HTML, in CSS, and in conditional blocks.
            <Link href="/help/controls" class="text-sky-500 hover:underline ml-1">Full controls reference →</Link>
          </p>

          <!-- Control types grid -->
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
            <div v-for="ctrl in controlTypes" :key="ctrl.type" class="rounded-md border border-border bg-background p-4">
              <div class="flex items-center gap-2 mb-2">
                <component :is="ctrl.icon" class="h-4 w-4 text-sky-500 shrink-0" />
                <span class="text-sm font-semibold font-mono">{{ ctrl.type }}</span>
              </div>
              <p class="text-sm text-muted-foreground mb-3">{{ ctrl.description }}</p>
              <div class="rounded bg-zinc-950 px-3 py-1.5 font-mono text-xs text-amber-400 overflow-x-auto">{{ ctrl.example }}</div>
            </div>
          </div>

          <!-- Power combo -->
          <div class="rounded-md border border-sky-500/20 overflow-hidden">
            <div class="px-4 py-2.5 bg-sky-950/20 border-b border-sky-500/20">
              <span class="text-xs font-mono text-sky-400">Power combo — boolean control + countdown timer + conditional class binding</span>
            </div>
            <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
              <div><span class="text-zinc-600 text-xs">// "show_timer" → boolean → "1" &nbsp;&nbsp; "round_timer" → timer → countdown, 300s base</span></div>
              <div class="mt-2"></div>
              <div><span class="text-sky-400">[[[if:c:show_timer]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"timer </span><span class="text-sky-400">[[[if:c:round_timer &lt;= 10]]]</span><span class="text-emerald-400">danger</span><span class="text-sky-400">[[[endif]]]</span><span class="text-emerald-400">"</span><span class="text-zinc-500">&gt;</span></div>
              <div>&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-amber-400">[[[c:round_timer]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div><span class="text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>
          <p class="text-sm text-muted-foreground mt-3">
            The timer ticks at 250ms resolution. The <code class="bg-zinc-900 text-amber-400 px-1 rounded text-xs">danger</code> class applies automatically when the countdown reaches 10 seconds. Flip the boolean from the dashboard to show or hide the block — live.
          </p>
        </div>
      </div>
    </section>

    <!-- 03 — Conditionals -->
    <section class="py-24 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <Badge variant="outline" class="text-xs font-mono px-3 py-1 mb-4">03 — Conditional Rendering</Badge>
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">A comparison engine in your template.</h2>
          <p class="text-muted-foreground max-w-2xl mb-12 text-lg">
            Any tag — Twitch data, control value, or event payload — can drive a conditional block.
            Evaluated client-side in the overlay with no server round-trips and no <code class="text-sm bg-muted px-1.5 rounded">eval()</code>.
            Nesting supported up to 10 levels deep.
            <Link href="/help" class="text-sky-500 hover:underline ml-1">Full syntax reference →</Link>
          </p>

          <div class="grid lg:grid-cols-2 gap-8 mb-10">
            <div>
              <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-widest mb-4">Syntax</h3>
              <div class="rounded-md border border-border overflow-hidden">
                <div class="bg-zinc-950 p-5 font-mono text-sm leading-7">
                  <div><span class="text-sky-400">[[[if:variable operator value]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-400">[[[elseif:variable operator value]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-400">[[[else]]]</span></div>
                  <div>&nbsp;&nbsp;<span class="text-zinc-500">...</span></div>
                  <div><span class="text-sky-400">[[[endif]]]</span></div>
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-widest mb-4">Operators</h3>
              <div class="rounded-md border border-border overflow-hidden divide-y divide-border">
                <div v-for="[op, label] in [['=','Equal'],['!=','Not equal'],['>','Greater than'],['<','Less than'],['>=','Greater than or equal'],['<=','Less than or equal']]" :key="op" class="flex items-center gap-4 px-4 py-2.5 bg-background">
                  <code class="font-mono text-sm text-amber-400 w-8 shrink-0">{{ op }}</code>
                  <span class="text-sm text-muted-foreground">{{ label }}</span>
                </div>
              </div>
              <p class="text-xs text-muted-foreground mt-3">Numeric comparisons are numeric. Truthy check treats <code class="bg-muted px-1 rounded">"0"</code>, <code class="bg-muted px-1 rounded">"false"</code>, and empty string as false.</p>
            </div>
          </div>

          <div class="rounded-md border border-border overflow-hidden">
            <div class="px-4 py-2.5 bg-muted/50 border-b border-border">
              <span class="text-xs text-muted-foreground font-mono">Language-aware overlay + milestone block</span>
            </div>
            <div class="bg-zinc-950 p-5 font-mono text-sm leading-7 overflow-x-auto">
              <div><span class="text-sky-400">[[[if:channel_language = en]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-zinc-300">Welcome to the stream</span><span class="text-zinc-500">&lt;/p&gt;</span></div>
              <div><span class="text-sky-400">[[[elseif:channel_language = es]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-zinc-300">Bienvenidos al stream</span><span class="text-zinc-500">&lt;/p&gt;</span></div>
              <div><span class="text-sky-400">[[[else]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;p&gt;</span><span class="text-zinc-300">Welcome</span><span class="text-zinc-500">&lt;/p&gt;</span></div>
              <div><span class="text-sky-400">[[[endif]]]</span></div>
              <div class="mt-3"></div>
              <div><span class="text-sky-400">[[[if:followers_total &gt;= 10000]]]</span></div>
              <div>&nbsp;&nbsp;<span class="text-zinc-500">&lt;div class=</span><span class="text-emerald-400">"tenk-badge"</span><span class="text-zinc-500">&gt;</span><span class="text-zinc-300">10K club</span><span class="text-zinc-500">&lt;/div&gt;</span></div>
              <div><span class="text-sky-400">[[[endif]]]</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 04 — Events -->
    <section class="py-24 bg-muted/20 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <Badge variant="outline" class="text-xs font-mono px-3 py-1 mb-4">04 — Event Alerts</Badge>
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Every Twitch event. One syntax.</h2>
          <p class="text-muted-foreground max-w-2xl mb-12 text-lg">
            Assign an alert template to any EventSub event. When the event fires, Overlabels renders the template with the payload merged into the tag context, broadcasts the compiled alert to your overlay over WebSocket, and displays it with a configured transition and duration — all without any interaction from you.
          </p>

          <!-- Events grid -->
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-12">
            <div v-for="evt in twitchEvents" :key="evt.type" class="rounded-md border border-border bg-background p-4">
              <div class="text-sm font-semibold mb-1">{{ evt.label }}</div>
              <div class="font-mono text-xs text-muted-foreground mb-3 truncate">{{ evt.type }}</div>
              <div v-if="evt.tag !== '—'" class="rounded bg-zinc-950 px-2.5 py-1.5 font-mono text-xs text-amber-400">[[[{{ evt.tag }}]]]</div>
              <div v-else class="rounded bg-zinc-950 px-2.5 py-1.5 font-mono text-xs text-zinc-600">no payload</div>
            </div>
          </div>

          <!-- Alert pipeline -->
          <div class="rounded-md border border-border overflow-hidden">
            <div class="px-4 py-2.5 bg-muted/50 border-b border-border">
              <span class="text-xs text-muted-foreground font-mono">What happens when a raid fires</span>
            </div>
            <div class="divide-y divide-border/50">
              <div v-for="(step, i) in alertPipelineSteps" :key="i" class="flex items-start gap-4 px-5 py-3.5 bg-background hover:bg-muted/30 transition-colors">
                <span class="font-mono text-xs text-sky-500/70 shrink-0 mt-0.5">{{ String(i + 1).padStart(2, '0') }}</span>
                <span class="text-sm text-foreground">{{ step }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 05 — Kits & Forking -->
    <section class="py-24 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <Badge variant="outline" class="text-xs font-mono px-3 py-1 mb-4">05 — Kits &amp; Forking</Badge>
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">Good design compounds.</h2>
          <p class="text-muted-foreground max-w-2xl mb-12 text-lg">
            Any public template or kit can be forked. One click, one copy, fully yours to modify, extend, or break. An Overlay Kit is a collection of templates — a static overlay, a follower alert, a subscription alert, a raid alert — designed as a cohesive visual system. Fork the kit, get everything at once.
          </p>

          <div class="grid sm:grid-cols-3 gap-6">
            <div class="rounded-md border border-border p-6 bg-background">
              <GitFork class="h-8 w-8 text-sky-500 mb-4" />
              <h3 class="font-semibold mb-2">Fork anything public</h3>
              <p class="text-sm text-muted-foreground">Every public template is a starting point. Fork it, own it, ship it. The original is always untouched.</p>
            </div>
            <div class="rounded-md border border-border p-6 bg-background">
              <Layers class="h-8 w-8 text-sky-500 mb-4" />
              <h3 class="font-semibold mb-2">Overlay Kits</h3>
              <p class="text-sm text-muted-foreground">Collections of templates sharing a visual language. Fork the kit, get the whole system. No assembly required.</p>
            </div>
            <div class="rounded-md border border-border p-6 bg-background">
              <Shield class="h-8 w-8 text-sky-500 mb-4" />
              <h3 class="font-semibold mb-2">Controls carry over</h3>
              <p class="text-sm text-muted-foreground">Forking a template with controls opens the Import Wizard. Pick which controls come with the fork.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 06 — Zero to Overlay -->
    <section class="py-24 bg-muted/20 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
          <Badge variant="outline" class="text-xs font-mono px-3 py-1 mb-4">06 — Zero to Overlay</Badge>
          <h2 class="text-3xl sm:text-4xl font-bold mb-4">From signup to OBS in minutes.</h2>
          <p class="text-muted-foreground max-w-2xl mb-12 text-lg">
            When you sign up, Overlabels runs an automated onboarding pipeline: generates your per-user webhook secret, forks the starter kit into your account, auto-assigns alert templates to event types, and generates your full personalised tag set from your live Twitch data. You arrive to a working overlay.
          </p>

          <div class="grid sm:grid-cols-2 gap-10">
            <div>
              <h3 class="font-semibold mb-5 flex items-center gap-2 text-sm uppercase tracking-widest text-muted-foreground">
                <Zap class="h-4 w-4 text-sky-500" /> Automated on signup
              </h3>
              <ul class="space-y-4 text-sm">
                <li class="flex gap-3 items-start">
                  <span class="text-sky-500 font-mono text-xs mt-0.5 shrink-0">01</span>
                  <span class="text-muted-foreground">Per-user webhook secret generated for HMAC-SHA256 validation</span>
                </li>
                <li class="flex gap-3 items-start">
                  <span class="text-sky-500 font-mono text-xs mt-0.5 shrink-0">02</span>
                  <span class="text-muted-foreground">Starter kit forked directly into your account</span>
                </li>
                <li class="flex gap-3 items-start">
                  <span class="text-sky-500 font-mono text-xs mt-0.5 shrink-0">03</span>
                  <span class="text-muted-foreground">Alert templates auto-assigned to event types by keyword matching</span>
                </li>
                <li class="flex gap-3 items-start">
                  <span class="text-sky-500 font-mono text-xs mt-0.5 shrink-0">04</span>
                  <span class="text-muted-foreground">Full tag set generated from your live Twitch channel data</span>
                </li>
              </ul>
            </div>

            <div>
              <h3 class="font-semibold mb-5 flex items-center gap-2 text-sm uppercase tracking-widest text-muted-foreground">
                <Code2 class="h-4 w-4 text-sky-500" /> Personalised testing page
              </h3>
              <p class="text-sm text-muted-foreground mb-4">
                The <code class="bg-zinc-900 text-sky-400 px-1.5 py-0.5 rounded text-xs font-mono">/testing</code> page gives you ready-to-run
                <a href="https://github.com/twitchdev/twitch-cli" target="_blank" rel="noopener" class="text-sky-500 hover:underline">Twitch CLI</a>
                commands for every event type, pre-filled with your credentials.
              </p>
              <div class="rounded-md bg-zinc-950 p-4 font-mono text-xs leading-6 overflow-x-auto">
                <div><span class="text-zinc-500">$ twitch event trigger channel.follow \</span></div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;--transport=webhook \</span></div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;-F </span><span class="text-emerald-400">https://overlabels.com/api/twitch/webhook</span><span class="text-zinc-500"> \</span></div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;-s </span><span class="text-amber-400">your_webhook_secret</span><span class="text-zinc-500"> \</span></div>
                <div><span class="text-zinc-500">&nbsp;&nbsp;--to-user </span><span class="text-amber-400">your_twitch_id</span></div>
              </div>
              <p class="text-xs text-muted-foreground mt-3">Commands are blurred by default and revealed on hover. No secrets exposed in source.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section id="get-started" class="py-24 border-b border-border/50">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center">
          <h2 class="text-4xl sm:text-5xl font-bold mb-6 tracking-tight">
            Ship your overlay.<br />
            <span class="text-sky-500">Free. Forever.</span>
          </h2>
          <p class="text-muted-foreground text-lg mb-10 max-w-lg mx-auto">
            No paywalls. No tiers. No artificial limits. Everything you create is yours.
            The whole thing is open source.
          </p>

          <div v-if="$page.props.auth.user" class="flex flex-col items-center gap-4">
            <Link :href="route('dashboard.index')" class="btn btn-primary text-base px-8">
              Go to Dashboard <ArrowRight class="ml-2 h-5 w-5" />
            </Link>
          </div>
          <div v-else class="flex flex-col items-center gap-6">
            <LoginSocial />
            <p class="text-xs text-muted-foreground">
              Authenticate with Twitch. No email required. Revoke access anytime from your Twitch settings.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="py-12">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-8">
          <div class="flex items-center gap-2.5">
            <img src="/favicon.png" alt="" class="w-8 h-8" />
            <span class="font-semibold">Overlabels</span>
            <Badge variant="outline" class="text-xs">Beta</Badge>
          </div>
          <div class="flex flex-wrap items-center gap-6 text-sm text-muted-foreground">
            <Link href="/help" class="hover:text-foreground transition-colors">Docs</Link>
            <Link href="/help/controls" class="hover:text-foreground transition-colors">Controls</Link>
            <Link href="/manifesto" class="hover:text-foreground transition-colors">Manifesto</Link>
            <Link href="/terms" class="hover:text-foreground transition-colors">Terms</Link>
            <Link href="/privacy" class="hover:text-foreground transition-colors">Privacy</Link>
            <a href="https://github.com/jasperfrontend/overlabels" target="_blank" rel="noopener" class="hover:text-foreground transition-colors flex items-center gap-1.5">
              GitHub <Github class="h-4 w-4" />
            </a>
          </div>
        </div>
        <div class="text-center text-xs text-muted-foreground border-t border-border/50 pt-8">
          Made by JasperDiscovers for the Twitch streaming community. Forever free, forever open.<br />
          FAQ: Will you support Kick.com? No.
        </div>
      </div>
    </footer>
  </div>
</template>
