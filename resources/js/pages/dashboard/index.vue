<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Radio, Sparkles, Zap } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import EventsTable from '@/components/EventsTable.vue';
import ProductIcon from '@/components/product-icons/ProductIcon.vue';
import { hasProductIcon } from '@/components/product-icons/registry';
import ProductStrip from '@/components/ProductStrip.vue';
import WelcomeBanner from '@/components/WelcomeBanner.vue';
import UpdatesList from '@/components/UpdatesList.vue';
import WelcomeDialog from '@/components/WelcomeDialog.vue';
import type { AppPageProps, InstalledProduct, Update, WhatsNew } from '@/types';

// The dashboard is a lobby, not an inventory. Four things, in the order a
// tired streamer wants them: hello, the ONE thing worth doing next, what is on
// your stream, and what happened lately. Every list of overlays and alerts
// lives on its own page; this one only points.

interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

type NextUp =
  | { kind: 'finish'; slug: string; name: string; hero: string | null; remaining: number; todo: string | null; href: string }
  | { kind: 'try'; slug: string; name: string; hero: string | null; description: string; href: string };

/** An Overlabels Overload Report (#345); see DashboardController::overloadReports(). */
interface OverloadReportCard {
  url: string;
  started_at: string;
  summary: string;
  expires_at: string;
}

/** A feature note written at /admin/highlights; see DashboardController::index(). */
interface Highlight {
  id: number;
  body: string;
  link_url: string;
  link_label: string;
}

interface LastStream {
  started_at: string;
  ended_at: string | null;
  minutes: number;
  moments: number;
  live: boolean;
}

const props = defineProps<{
  /** No product installed yet - see DashboardController::index(). */
  isNewUser: boolean;
  /** Listed products this account has installed, newest first. */
  installedProducts: InstalledProduct[];
  /** The one door: finish a setup, try a game, or null for "all set". */
  nextUp: NextUp | null;
  showWelcomeDialog: boolean;
  lastStream: LastStream | null;
  recentEvents: UnifiedEvent[];
  overloadReports: OverloadReportCard[];
  highlights: Highlight[];
  whatsNew: WhatsNew;
  /** The five newest published posts from /updates. */
  latestUpdates: Pick<Update, 'id' | 'slug' | 'title' | 'excerpt' | 'tags' | 'published_at'>[];
}>();

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth.user);
// Day and month names here sit inside English sentences, so they are English.
// The account locale is for what overlays print on stream, not for this copy:
// with nl-NL it produced "woensdag 18 november" above "Good evening".
const locale = 'en-US';

// Said the way a person would say it, from the viewer's own clock.
const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 5) return 'Up late';
  if (hour < 12) return 'Good morning';
  if (hour < 18) return 'Good afternoon';
  return 'Good evening';
});

const today = computed(() => new Intl.DateTimeFormat(locale, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date()));

function duration(minutes: number, short = false): string {
  if (minutes < 1) return short ? '<1 min' : 'under a minute';
  if (minutes < 60) return `${minutes} min`;
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

function relativeDay(iso: string): string {
  const then = new Date(iso);
  const now = new Date();
  const startOf = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  const days = Math.round((startOf(now) - startOf(then)) / 86_400_000);
  if (days <= 0) return 'today';
  if (days === 1) return 'yesterday';
  if (days < 7) return `on ${new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(then)}`;
  return `on ${new Intl.DateTimeFormat(locale, { month: 'long', day: 'numeric' }).format(then)}`;
}

// A live stream where nothing has happened yet suggests one installed game,
// picked at random once per page load so the line does not change under you.
// Ready games first; one still being set up beats suggesting an install.
const pickSeed = Math.random();
const liveGame = computed(() => {
  const games = props.installedProducts.filter((p) => p.game);
  const ready = games.filter((p) => (p.remaining ?? 0) === 0);
  const pool = ready.length > 0 ? ready : games;
  return pool.length > 0 ? pool[Math.floor(pickSeed * pool.length)] : null;
});

const suggestedGame = computed(() => (props.lastStream?.live && props.lastStream.moments === 0 ? liveGame.value : null));

// One sentence under the greeting. Read from the server's session record, not
// the live socket: useStreamState leaves the alerts channel when it unmounts,
// and the header already holds the one live copy.
const streamLine = computed(() => {
  const s = props.lastStream;
  if (!s) return 'Your stream has not gone live with Overlabels yet. Everything below is ready for when it does.';
  // With a game to suggest, the template writes the line itself so the game's
  // name can be a link (see suggestedGame).
  if (s.live && s.moments === 0) return 'You are live right now. Why not install a game and create some chat interactivity!';
  if (s.live) return `You are live right now. ${s.moments === 1 ? 'One moment' : `${s.moments} moments`} so far - go have fun.`;
  const base = `You last went live ${relativeDay(s.started_at)}, for ${duration(s.minutes)}`;
  if (s.moments === 0) return `${base}.`;
  return `${base}, with ${s.moments === 1 ? 'one moment' : `${s.moments} moments`} on screen.`;
});

const latestNews = computed(() => props.whatsNew.items[0] ?? null);

// Two rows: what chat plays, and everything else that is on the stream.
const games = computed(() => props.installedProducts.filter((p) => p.game));
const others = computed(() => props.installedProducts.filter((p) => !p.game));

const breadcrumbs = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
  <Head>
    <title>Dashboard</title>
    <meta name="description" content="Dashboard for Overlabels - My Twitch overlay hub" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <WelcomeDialog :show="props.showWelcomeDialog" />

    <div class="lobby mx-auto flex w-full max-w-6xl flex-col gap-12 px-4 py-8 md:px-8 md:py-12">
      <!-- New accounts keep the onboarding exactly as it was: it works. -->
      <template v-if="props.isNewUser">
        <!-- No close button: it stays until the first product is installed, for
             everyone who closed the welcome dialog without reading it. -->
        <WelcomeBanner class="rise" :name="user.name" :avatar="user.avatar" />
      </template>

      <template v-else>
        <!-- 1. Hello. -->
        <header class="rise flex flex-col gap-6 md:flex-row md:items-end md:justify-between" style="--d: 0ms">
          <div class="flex min-w-0 items-center gap-5">
            <img v-if="user.avatar" :src="user.avatar" alt="" class="hidden size-20 shrink-0 border-2 border-[#1DAAFF] object-cover sm:block" />
            <div class="min-w-0">
              <p class="font-mono text-xs tracking-widest text-[#0A78BE] uppercase dark:text-[#1DAAFF]">{{ today }}</p>
              <h1 class="mt-1 text-4xl leading-none font-bold tracking-tight text-foreground md:text-6xl">
                {{ greeting }}, <span class="name-fit">{{ user.name }}</span>
              </h1>
              <p class="mt-3 flex items-start gap-2 text-base text-pretty text-foreground md:text-lg">
                <span v-if="props.lastStream?.live" class="relative mt-2 flex size-2.5 shrink-0">
                  <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75" />
                  <span class="relative inline-flex size-2.5 rounded-full bg-green-500" />
                </span>
                <span v-if="suggestedGame">
                  You are live right now. Why not play a game of
                  <Link
                    :href="route('products.manage', suggestedGame.slug)"
                    class="font-medium text-[#0A78BE] underline-offset-2 hover:underline dark:text-[#1DAAFF]"
                    >{{ suggestedGame.name }}</Link
                  >
                  with your viewers?
                </span>
                <span v-else>{{ streamLine }}</span>
              </p>
            </div>
          </div>

          <Link
            v-if="latestNews"
            :href="latestNews.href"
            class="group inline-flex max-w-sm shrink-0 items-center gap-3 self-start border border-border px-4 py-3 text-sm transition-colors hover:border-[#1DAAFF] md:self-end"
          >
            <Sparkles class="size-4 shrink-0 text-[#0A78BE] dark:text-[#1DAAFF]" />
            <span class="min-w-0">
              <span class="block text-xs text-muted-foreground">New on Overlabels</span>
              <span class="block truncate font-medium text-foreground">{{ latestNews.title }}</span>
            </span>
            <ArrowRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1" />
          </Link>
        </header>

        <!-- 2. The one door. -->
        <section class="rise" style="--d: 90ms" aria-labelledby="next-up-heading">
          <!-- A product to try is one big link: the shadow and the button's hover
               arrive together when the card is hovered. A setup to finish keeps
               its standing shadow and its own button. -->
          <component
            :is="props.nextUp.kind === 'try' ? Link : 'div'"
            v-if="props.nextUp"
            :href="props.nextUp.kind === 'try' ? props.nextUp.href : undefined"
            class="grid border-2 border-foreground/90 bg-sidebar-accent md:grid-cols-[1fr_1.1fr]"
            :class="props.nextUp.kind === 'try' ? 'next-up-try group' : 'next-up'"
          >
            <div class="flex flex-col justify-center gap-4 p-6 md:p-10">
              <p class="inline-flex items-center gap-2 font-mono text-xs font-semibold tracking-widest text-[#0A78BE] uppercase dark:text-[#1DAAFF]">
                <Zap class="size-4" />
                Next up
              </p>

              <template v-if="props.nextUp.kind === 'finish'">
                <h2 id="next-up-heading" class="text-3xl leading-tight font-bold tracking-tight text-foreground md:text-4xl">
                  {{ props.nextUp.name }} is
                  {{ props.nextUp.remaining === 1 ? 'one step' : `${props.nextUp.remaining} steps` }}
                  from your stream
                </h2>
                <p v-if="props.nextUp.todo" class="max-w-md text-base text-foreground">
                  Next, {{ props.nextUp.todo }}. It takes a minute, and then it is live for your chat.
                </p>
                <Link :href="props.nextUp.href" class="btn btn-xl btn-primary mt-2 self-start">
                  Finish setting it up
                  <ArrowRight class="size-5" />
                </Link>
              </template>

              <template v-else>
                <h2 id="next-up-heading" class="text-3xl leading-tight font-bold tracking-tight text-foreground md:text-4xl">
                  Have you tried our {{ props.nextUp.name }} yet?
                </h2>
                <p class="line-clamp-3 max-w-md text-base text-foreground">{{ props.nextUp.description }}</p>
                <span class="btn btn-xl btn-primary mt-2 self-start group-hover:brightness-110">
                  Take a look
                  <ArrowRight class="size-5 transition-transform group-hover:translate-x-1" />
                </span>
              </template>
            </div>

            <component
              :is="props.nextUp.kind === 'try' ? 'div' : Link"
              :href="props.nextUp.kind === 'try' ? undefined : props.nextUp.href"
              class="group relative block overflow-hidden border-t-2 border-foreground/90 md:border-t-0 md:border-l-2"
              :tabindex="props.nextUp.kind === 'try' ? undefined : -1"
            >
              <img
                v-if="props.nextUp.hero"
                :src="props.nextUp.hero"
                alt=""
                class="aspect-video size-full origin-left object-cover object-left transition-transform duration-500 group-hover:scale-[1.03] md:aspect-auto"
              />
              <div v-else class="flex aspect-video size-full items-center justify-center bg-muted/30">
                <ProductIcon v-if="hasProductIcon(props.nextUp.slug)" :slug="props.nextUp.slug" class="size-24 text-foreground" />
              </div>
            </component>
          </component>

          <!-- Nothing to finish, nothing left to try. -->
          <div v-else class="next-up flex flex-col items-start gap-4 border-2 border-foreground/90 bg-sidebar-accent p-6 md:p-10">
            <p class="inline-flex items-center gap-2 font-mono text-xs font-semibold tracking-widest text-green-700 uppercase dark:text-green-400">
              <Check class="size-4" />
              All set
            </p>
            <h2 id="next-up-heading" class="text-3xl leading-tight font-bold tracking-tight text-foreground md:text-4xl">Nothing left to set up</h2>
            <!-- Read days later, so it states what is true about Overlabels, not
                 what the streamer is about to do. A new product is not installed
                 yet, so it really does take this card's place. -->
            <p class="max-w-xl text-base text-foreground">
              You have every Overlabels product installed and ready. When we release a new one, it shows up right here.
            </p>
            <Link :href="route('products.index', { category: 'installed' })" class="btn btn-secondary self-start">
              View your installed products
              <ArrowRight class="size-4" />
            </Link>
          </div>
        </section>

        <!-- 3. What is on the stream: the games chat plays, then everything
             else (chat feeds, emote bubbles, alerts). The second row only
             appears when there is something in it. -->
        <ProductStrip class="rise" style="--d: 180ms" title="Games on your stream" heading-id="games-heading" :products="games" />
        <ProductStrip
          v-if="others.length > 0"
          class="rise"
          style="--d: 225ms"
          title="Also on your stream"
          heading-id="others-heading"
          :products="others"
        />

        <!-- 3b. Worth knowing: feature notes written in the admin, all alike. -->
        <section v-if="props.highlights.length > 0" class="rise flex flex-col gap-4" style="--d: 250ms" aria-labelledby="highlights-heading">
          <h2 id="highlights-heading" class="text-2xl font-bold tracking-tight text-foreground">Did you know?</h2>
          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <!-- The whole card is the link, like the Last stream tile below. -->
            <Link
              v-for="highlight in props.highlights"
              :key="highlight.id"
              :href="highlight.link_url"
              class="tile group flex flex-col gap-4 border border-border p-6 transition-all duration-200 hover:-translate-y-1 hover:border-[#1DAAFF]"
            >
              <p class="text-sm text-foreground">{{ highlight.body }}</p>
              <span class="mt-auto inline-flex items-center gap-1 text-sm font-medium text-[#0A78BE] group-hover:underline dark:text-[#1DAAFF]">
                {{ highlight.link_label }}
                <ArrowRight class="size-3.5 transition-transform group-hover:translate-x-1" />
              </span>
            </Link>
          </div>
        </section>

        <!-- 4. Lately. -->
        <section class="rise grid gap-6 lg:grid-cols-[1fr_2fr]" style="--d: 270ms" aria-label="Lately">
          <!-- The whole block is the link once there is a stream to open. -->
          <component
            :is="props.lastStream ? Link : 'div'"
            :href="props.lastStream ? route('dashboard.stream-sessions') : undefined"
            class="flex flex-col gap-4 border border-border p-6"
            :class="props.lastStream ? 'tile group transition-all duration-200 hover:-translate-y-1 hover:border-[#1DAAFF]' : ''"
          >
            <h2 class="inline-flex items-center gap-2 text-sm font-semibold tracking-widest text-muted-foreground uppercase">
              <Radio class="size-4" />
              {{ props.lastStream?.live ? 'This stream' : 'Last stream' }}
            </h2>

            <template v-if="props.lastStream">
              <div class="flex items-baseline gap-6">
                <div>
                  <p class="text-4xl font-bold tracking-tight text-foreground tabular-nums">{{ duration(props.lastStream.minutes, true) }}</p>
                  <p class="text-xs text-muted-foreground">{{ props.lastStream.live ? 'so far' : relativeDay(props.lastStream.started_at) }}</p>
                </div>
                <div>
                  <p class="text-4xl font-bold tracking-tight text-foreground tabular-nums">{{ props.lastStream.moments }}</p>
                  <p class="text-xs text-muted-foreground">{{ props.lastStream.moments === 1 ? 'moment' : 'moments' }}</p>
                </div>
              </div>
              <span class="mt-auto inline-flex items-center gap-1 text-sm font-medium text-[#0A78BE] group-hover:underline dark:text-[#1DAAFF]">
                All your streams
                <ArrowRight class="size-3.5 transition-transform group-hover:translate-x-1" />
              </span>
            </template>
            <p v-else class="text-sm text-foreground">Go live once and your stream shows up here: how long, and how much happened.</p>
          </component>

          <div class="flex flex-col gap-4 border border-border p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h2 class="text-sm font-semibold tracking-widest text-muted-foreground uppercase">Latest moments</h2>
              <Link
                v-if="props.recentEvents.length > 0"
                :href="route('dashboard.recents')"
                class="inline-flex items-center gap-1 text-sm font-medium text-[#0A78BE] hover:underline dark:text-[#1DAAFF]"
              >
                Everything that happened
                <ArrowRight class="size-3.5" />
              </Link>
            </div>
            <EventsTable v-if="props.recentEvents.length > 0" :events="props.recentEvents" />
            <p v-else class="text-sm text-foreground">Quiet so far. Follows, subs, raids and donations land here the moment they happen.</p>
          </div>
        </section>

        <!-- 4b. Overload Reports: only once there is one. -->
        <section
          v-if="props.overloadReports.length > 0"
          class="rise flex flex-col gap-4 border border-border p-6"
          style="--d: 290ms"
          aria-labelledby="overload-heading"
        >
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="overload-heading" class="text-sm font-semibold tracking-widest text-muted-foreground uppercase">Overload Reports</h2>
            <Link
              :href="route('settings.overload-report')"
              class="inline-flex items-center gap-1 text-sm font-medium text-[#0A78BE] hover:underline dark:text-[#1DAAFF]"
            >
              Report settings
              <ArrowRight class="size-3.5" />
            </Link>
          </div>
          <ul class="flex flex-col gap-3">
            <li v-for="report in props.overloadReports" :key="report.url" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
              <a :href="report.url" target="_blank" rel="noopener" class="text-sm font-medium text-foreground hover:underline">
                {{ report.summary.charAt(0).toUpperCase() + report.summary.slice(1) }}
              </a>
              <span class="text-xs text-muted-foreground">
                {{ relativeDay(report.started_at) }}, kept until
                {{ new Intl.DateTimeFormat(locale, { month: 'long', day: 'numeric' }).format(new Date(report.expires_at)) }}
              </span>
            </li>
          </ul>
        </section>

        <!-- 5. The last five posts from /updates, in the /updates list design. -->
        <section v-if="props.latestUpdates.length > 0" class="rise flex flex-col" style="--d: 315ms" aria-labelledby="nerdy-gritty-heading">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="nerdy-gritty-heading" class="text-sm font-semibold tracking-widest text-muted-foreground uppercase">The Nerdy Gritty</h2>
            <Link
              :href="route('updates.index')"
              class="inline-flex items-center gap-1 text-sm font-medium text-[#0A78BE] hover:underline dark:text-[#1DAAFF]"
            >
              All updates
              <ArrowRight class="size-3.5" />
            </Link>
          </div>
          <UpdatesList :updates="props.latestUpdates" />
        </section>
      </template>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Sections rise in one after another, so the page arrives instead of
   appearing. --d staggers them; reduced motion gets the page at once. */
.rise {
  animation: lobby-rise 0.6s cubic-bezier(0.2, 0.7, 0.2, 1) both;
  animation-delay: var(--d, 0ms);
}

@keyframes lobby-rise {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

/* The one door gets the one hard shadow on the page: a solid offset in the
   primary blue, the app's square-cornered style turned up rather than a soft
   glow. */
.next-up {
  box-shadow: 8px 8px 0 0 #1daaff;
}

/* A product to try earns the shadow on hover (and keyboard focus) only. */
.next-up-try {
  transition: box-shadow 0.2s ease;
}

.next-up-try:hover,
.next-up-try:focus-visible {
  outline: none;
  box-shadow: 8px 8px 0 0 #1daaff;
}

/* Twitch names run to 25 characters with no break in them. The name keeps to
   one line: beside the greeting when it fits, on its own line when it does
   not, and cut with an ellipsis when even that is too narrow. The padding
   makes room for descenders, which the heading's leading-none would clip. */
.name-fit {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: bottom;
  padding-bottom: 0.12em;
  margin-bottom: -0.12em;
}

.tile:hover {
  box-shadow: 4px 4px 0 0 #1daaff;
}

@media (prefers-reduced-motion: reduce) {
  .rise {
    animation: none;
  }
  .tile,
  .tile * {
    transition: none !important;
  }
}
</style>
