<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Hourglass, PackageCheck, PackageX, Radio, Unlink, UserPlus } from '@lucide/vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';

const { formatDateTime } = useDateTimeFormat();

// Timestamps are Unix seconds, null = the account never reached that step.
interface FunnelRow {
  id: number;
  name: string;
  avatar: string | null;
  signed_up_at: number;
  welcomed_at: number | null;
  listing_at: number | null;
  listing_from: string | null;
  banner_at: number | null;
  dialog_at: number | null;
  product_at: number | null;
  product_slug: string | null;
  installed_at: number | null;
  installed_slug: string | null;
  on_stream_at: number | null;
  all_removed: boolean;
}

type InstallState = 'on_stream' | 'waiting' | 'broken' | 'uninstalled';

interface InstallRow {
  id: number;
  user_id: number;
  name: string;
  avatar: string | null;
  product_slug: string;
  installed_at: number;
  first_loaded_at: number | null;
  uninstalled_at: number | null;
  state: InstallState;
  overlay_deleted: boolean;
  no_links: boolean;
}

interface Totals {
  signed_up: number;
  welcomed: number;
  listing: number;
  banner: number;
  dialog: number;
  product: number;
  installed: number;
  on_stream: number;
}

interface InstallTotals {
  installed: number;
  on_stream: number;
  waiting: number;
  broken: number;
  uninstalled: number;
  uninstalled_after_showing: number;
}

const props = defineProps<{
  rows: FunnelRow[];
  totals: Totals;
  installs: InstallRow[];
  install_totals: InstallTotals;
  products: Record<string, string>;
  days: number;
  windows: number[];
  limited: boolean;
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Funnel', href: route('admin.funnel.index') },
];

// The steps in the order a new account walks them. The two sub-steps say
// which welcome link carried people to the listing, and are not part of the
// drop-off between steps.
const steps = computed(() => [
  { label: 'Signed up', count: props.totals.signed_up, sub: false },
  { label: 'Saw the welcome dialog', count: props.totals.welcomed, sub: false },
  { label: 'Reached the products page', count: props.totals.listing, sub: false },
  { label: 'clicked the banner', count: props.totals.banner, sub: true },
  { label: 'clicked the dialog button', count: props.totals.dialog, sub: true },
  { label: 'Opened a product page', count: props.totals.product, sub: false },
  { label: 'Installed a product', count: props.totals.installed, sub: false },
  { label: 'Shown on stream', count: props.totals.on_stream, sub: false, goal: true },
]);

// Main steps carry a number and the loss since the previous main step.
const numberedSteps = computed(() => {
  let previous: number | null = null;
  let n = 0;
  return steps.value.map((step) => {
    if (step.sub) return { ...step, n: null, lost: null };
    n += 1;
    const lost = previous === null ? null : previous - step.count;
    previous = step.count;
    return { ...step, n, lost };
  });
});

function percent(count: number, of = props.totals.signed_up): string {
  if (of === 0) return '-';
  return `${Math.round((count / of) * 100)}%`;
}

function width(count: number, of = props.totals.signed_up): string {
  return of ? `${(count / of) * 100}%` : '0%';
}

function windowLabel(days: number): string {
  return days === 0 ? 'All time' : `${days} days`;
}

function productName(slug: string | null): string | null {
  if (!slug) return null;
  return props.products[slug] ?? slug;
}

// How long after a starting moment a step happened, as one short unit.
function since(from: number, seconds: number | null): string | null {
  if (seconds === null) return null;
  const delta = Math.max(0, seconds - from);
  if (delta < 60) return `+${delta}s`;
  if (delta < 3600) return `+${Math.round(delta / 60)}m`;
  if (delta < 86400) return `+${Math.round(delta / 3600)}h`;
  return `+${Math.round(delta / 86400)}d`;
}

function after(row: FunnelRow, seconds: number | null): string | null {
  return since(row.signed_up_at, seconds);
}

function sourceLabel(from: string | null): string | null {
  if (from === 'welcome-banner') return 'via banner';
  if (from === 'welcome-dialog') return 'via dialog';
  if (from === 'direct') return 'other link';
  return null;
}

// Where an account stands now, as one badge.
function status(row: FunnelRow): { label: string; tone: string } {
  if (row.all_removed) return { label: 'Uninstalled', tone: 'removed' };
  if (row.on_stream_at !== null) return { label: 'On stream', tone: 'goal' };
  if (row.installed_at !== null) return { label: 'Installed', tone: 'reached' };
  if (row.product_at !== null) return { label: 'Opened a product', tone: 'plain' };
  if (row.listing_at !== null) return { label: 'Reached products', tone: 'plain' };
  if (row.welcomed_at !== null) return { label: 'Saw the dialog', tone: 'plain' };
  return { label: 'Signed up', tone: 'plain' };
}

// The journey as a row of segments for the card view.
function journey(row: FunnelRow): boolean[] {
  return [row.welcomed_at, row.listing_at, row.product_at, row.installed_at, row.on_stream_at].map((at) => at !== null);
}

const toneClass: Record<string, string> = {
  goal: 'border-cyan-400/60 text-cyan-600 dark:text-cyan-300',
  reached: 'border-foreground/30 text-foreground',
  removed: 'border-red-400/60 text-red-600 dark:text-red-400',
  plain: 'border-sidebar text-muted-foreground',
};

const installStates: Record<InstallState, { label: string; icon: typeof Radio; badge: string; dot: string }> = {
  on_stream: { label: 'On stream', icon: Radio, badge: 'border-cyan-400/60 text-cyan-600 dark:text-cyan-300', dot: 'bg-cyan-400' },
  waiting: { label: 'Not shown yet', icon: Hourglass, badge: 'border-sidebar text-muted-foreground', dot: 'bg-foreground/50' },
  broken: { label: 'Broken', icon: Unlink, badge: 'border-pink-400/60 text-pink-600 dark:text-pink-400', dot: 'bg-pink-400' },
  uninstalled: { label: 'Uninstalled', icon: PackageX, badge: 'border-red-400/60 text-red-600 dark:text-red-400', dot: 'bg-red-400' },
};

const kpis = computed(() => [
  { label: 'Signed up', value: props.totals.signed_up, note: windowLabel(props.days).toLowerCase(), icon: UserPlus, goal: false },
  {
    label: 'Installed a product',
    value: props.totals.installed,
    note: `${percent(props.totals.installed)} of signups`,
    icon: PackageCheck,
    goal: false,
  },
  { label: 'Shown on stream', value: props.totals.on_stream, note: `${percent(props.totals.on_stream)} of signups`, icon: Radio, goal: true },
  {
    label: 'Uninstalls',
    value: props.install_totals.uninstalled,
    note: `${props.install_totals.uninstalled_after_showing} after being on stream`,
    icon: PackageX,
    goal: false,
  },
]);

const installSummary = computed(() =>
  (['on_stream', 'waiting', 'broken', 'uninstalled'] as InstallState[]).map((state) => ({
    state,
    count: props.install_totals[state],
    ...installStates[state],
  })),
);

function brokenReason(install: InstallRow): string | null {
  const reasons = [install.overlay_deleted ? 'overlay deleted' : null, install.no_links ? 'no overlay links left' : null].filter(Boolean);
  return reasons.length ? reasons.join(', ') : null;
}
</script>

<template>
  <Head><title>Admin - Funnel</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <PageHeader title="Welcome funnel" title-class="text-2xl font-bold">
        <template #actions>
          <div class="inline-flex overflow-hidden rounded-md border border-sidebar">
            <Link
              v-for="w in windows"
              :key="w"
              :href="route('admin.funnel.index', { days: w })"
              preserve-scroll
              class="cursor-pointer border-l border-sidebar px-3 py-1.5 text-sm first:border-l-0"
              :class="w === days ? 'bg-foreground text-background' : 'hover:bg-card'"
            >
              {{ windowLabel(w) }}
            </Link>
          </div>
        </template>
      </PageHeader>

      <!-- Headline numbers -->
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div
          v-for="kpi in kpis"
          :key="kpi.label"
          class="flex flex-col gap-2 rounded-lg border bg-card/40 p-4"
          :class="kpi.goal ? 'border-cyan-400/60' : 'border-sidebar'"
        >
          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <span
              class="flex size-7 items-center justify-center rounded-md border"
              :class="kpi.goal ? 'border-cyan-400/60 text-cyan-500' : 'border-sidebar'"
            >
              <component :is="kpi.icon" class="size-4" />
            </span>
            {{ kpi.label }}
          </div>
          <div class="text-3xl font-bold tabular-nums sm:text-4xl">{{ kpi.value }}</div>
          <div class="text-xs text-muted-foreground">{{ kpi.note }}</div>
        </div>
      </div>

      <!-- Step totals -->
      <section class="rounded-lg border border-sidebar">
        <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-sidebar px-4 py-3">
          <h2 class="font-semibold">From signup to stream</h2>
          <p class="text-xs text-muted-foreground">Share of signups that reached each step at least once</p>
        </header>
        <div class="flex flex-col py-2">
          <div
            v-for="step in numberedSteps"
            :key="step.label"
            class="grid grid-cols-[1.75rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-4 py-2 text-sm sm:grid-cols-[1.75rem_minmax(0,16rem)_minmax(0,1fr)_7rem]"
          >
            <span
              v-if="step.n !== null"
              class="flex size-6 items-center justify-center rounded-full border text-xs font-semibold tabular-nums"
              :class="step.goal ? 'border-cyan-400 text-cyan-500' : 'border-sidebar text-muted-foreground'"
            >
              {{ step.n }}
            </span>
            <span v-else class="ml-3 h-4 w-3 rounded-bl border-b border-l border-sidebar" />

            <span class="min-w-0 truncate" :class="step.sub ? 'text-muted-foreground' : 'font-medium'">{{ step.label }}</span>

            <div class="col-span-3 col-start-2 row-start-2 h-2.5 rounded-full bg-card sm:col-span-1 sm:col-start-3 sm:row-start-1">
              <div
                class="h-2.5 rounded-full"
                :class="step.sub ? 'bg-cyan-400/35' : step.goal ? 'bg-cyan-400' : 'bg-cyan-400/75'"
                :style="{ width: width(step.count) }"
                :title="`${step.count} of ${totals.signed_up}`"
              />
            </div>

            <span class="row-start-1 flex items-baseline justify-end gap-2 tabular-nums sm:col-start-4">
              <span class="font-semibold">{{ step.count }}</span>
              <span class="w-10 text-right text-xs text-muted-foreground">{{ percent(step.count) }}</span>
              <span
                class="hidden w-8 text-right text-xs text-red-500/80 sm:inline"
                :title="step.lost && step.lost > 0 ? `${step.lost} fewer than the step before` : undefined"
              >
                {{ step.lost && step.lost > 0 ? `-${step.lost}` : '' }}
              </span>
            </span>
          </div>
        </div>
        <p class="border-t border-sidebar px-4 py-3 text-xs text-muted-foreground">
          Products page, product page and welcome-link clicks are recorded from October 23rd 2026 on, and "shown on stream" from November 15th 2026
          (earlier installs read back 90 days of overlay loads). Older accounts show blanks there even if they got further.
        </p>
      </section>

      <!-- Product installs -->
      <section class="rounded-lg border border-sidebar">
        <header class="flex flex-col gap-3 border-b border-sidebar px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="font-semibold">Product installs</h2>
            <p class="text-xs text-muted-foreground">Every install in this window, not just an account's first, and where it stands now</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="item in installSummary"
              :key="item.state"
              class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs"
              :class="item.badge"
            >
              <component :is="item.icon" class="size-3.5" />
              <span class="font-semibold tabular-nums">{{ item.count }}</span>
              {{ item.label.toLowerCase() }}
            </span>
          </div>
        </header>

        <EmptyState v-if="installs.length === 0" message="Nothing was installed in this window." class="m-4" />

        <ul v-else class="divide-y divide-sidebar">
          <li
            v-for="install in installs"
            :key="install.id"
            class="grid grid-cols-1 gap-3 px-4 py-3 text-sm md:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto] md:items-center"
          >
            <div class="flex min-w-0 items-center gap-2.5">
              <img v-if="install.avatar" :src="install.avatar" alt="" class="size-8 shrink-0 rounded-full object-cover" />
              <span v-else class="size-8 shrink-0 rounded-full border border-sidebar" />
              <div class="min-w-0">
                <Link :href="route('admin.users.show', install.user_id)" class="block cursor-pointer truncate font-medium hover:underline">
                  {{ install.name }}
                </Link>
                <span class="block truncate text-xs text-muted-foreground">{{ productName(install.product_slug) }}</span>
              </div>
            </div>

            <!-- Installed, on stream, uninstalled: three stops on one line -->
            <ol class="flex min-w-0 items-center text-xs">
              <li class="flex shrink-0 items-center gap-1.5" :title="formatDateTime(install.installed_at)">
                <span class="size-2.5 rounded-full bg-foreground/70" />
                <span>Installed</span>
                <span class="text-muted-foreground">{{ formatDateTime(install.installed_at) }}</span>
              </li>

              <li class="mx-2 h-px max-w-24 min-w-4 flex-1" :class="install.first_loaded_at !== null ? 'bg-cyan-400/70' : 'bg-sidebar'" />
              <li
                class="flex shrink-0 items-center gap-1.5"
                :title="install.first_loaded_at !== null ? formatDateTime(install.first_loaded_at) : 'No overlay link has shown it yet'"
              >
                <span class="size-2.5 rounded-full" :class="install.first_loaded_at !== null ? 'bg-cyan-400' : 'border border-muted-foreground/50'" />
                <span :class="install.first_loaded_at !== null ? '' : 'text-muted-foreground'">On stream</span>
                <span v-if="install.first_loaded_at !== null" class="text-muted-foreground">{{
                  since(install.installed_at, install.first_loaded_at)
                }}</span>
              </li>

              <template v-if="install.state === 'uninstalled'">
                <li class="mx-2 h-px max-w-24 min-w-4 flex-1 bg-red-400/60" />
                <li
                  class="flex shrink-0 items-center gap-1.5"
                  :title="install.uninstalled_at !== null ? formatDateTime(install.uninstalled_at) : 'Uninstalled before this was recorded'"
                >
                  <span class="size-2.5 rounded-full bg-red-400" />
                  <span>Uninstalled</span>
                  <span class="text-muted-foreground">{{
                    install.uninstalled_at !== null ? since(install.installed_at, install.uninstalled_at) : 'date unknown'
                  }}</span>
                </li>
              </template>
            </ol>

            <div class="flex flex-col items-start gap-1 md:items-end">
              <span
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                :class="installStates[install.state].badge"
              >
                <component :is="installStates[install.state].icon" class="size-3.5" />
                {{ installStates[install.state].label }}
              </span>
              <span v-if="brokenReason(install)" class="text-xs text-muted-foreground">{{ brokenReason(install) }}</span>
            </div>
          </li>
        </ul>
      </section>

      <!-- Signups -->
      <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Signups</h2>
        <p v-if="limited" class="text-sm text-muted-foreground">Showing the newest {{ rows.length }} accounts only.</p>

        <!-- Card view (< lg) -->
        <div class="space-y-2 lg:hidden">
          <EmptyState v-if="rows.length === 0" message="Nobody signed up in this window." />
          <div v-for="row in rows" :key="`card-${row.id}`" class="rounded-lg border border-sidebar p-3 text-sm">
            <div class="flex items-center justify-between gap-2">
              <Link :href="route('admin.users.show', row.id)" class="flex min-w-0 cursor-pointer items-center gap-2 font-medium hover:underline">
                <img v-if="row.avatar" :src="row.avatar" alt="" class="size-6 shrink-0 rounded-full object-cover" />
                <span class="truncate">{{ row.name }}</span>
              </Link>
              <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium" :class="toneClass[status(row).tone]">{{
                status(row).label
              }}</span>
            </div>
            <div class="mt-2.5 flex gap-1" :title="'Dialog, products page, product page, installed, on stream'">
              <span
                v-for="(reached, i) in journey(row)"
                :key="i"
                class="h-1.5 flex-1 rounded-full"
                :class="reached ? (i === 4 ? 'bg-cyan-400' : 'bg-cyan-400/60') : 'bg-card'"
              />
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <span>Signed up {{ formatDateTime(row.signed_up_at) }}</span>
              <span v-if="row.listing_at !== null">Products {{ after(row, row.listing_at) }} {{ sourceLabel(row.listing_from) }}</span>
              <span v-if="row.product_slug">Opened {{ productName(row.product_slug) }}</span>
              <span v-if="row.installed_at !== null">Installed {{ productName(row.installed_slug) ?? '' }} {{ after(row, row.installed_at) }}</span>
              <span v-if="row.on_stream_at !== null">On stream {{ after(row, row.on_stream_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Table (>= lg) -->
        <div class="hidden overflow-x-auto rounded-lg border border-sidebar lg:block">
          <table class="w-full text-sm">
            <thead class="bg-card text-left text-xs tracking-wide text-muted-foreground uppercase">
              <tr>
                <th class="px-3 py-2.5 font-medium">Account</th>
                <th class="px-3 py-2.5 font-medium">Signed up</th>
                <th class="px-3 py-2.5 font-medium">Dialog</th>
                <th class="px-3 py-2.5 font-medium">Products page</th>
                <th class="px-3 py-2.5 font-medium">Banner</th>
                <th class="px-3 py-2.5 font-medium">Product page</th>
                <th class="px-3 py-2.5 font-medium">Installed</th>
                <th class="px-3 py-2.5 font-medium">On stream</th>
                <th class="px-3 py-2.5 text-right font-medium">Now</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" class="border-t border-sidebar hover:bg-card/50">
                <td class="px-3 py-2.5">
                  <Link :href="route('admin.users.show', row.id)" class="flex cursor-pointer items-center gap-2 font-medium hover:underline">
                    <img v-if="row.avatar" :src="row.avatar" alt="" class="size-6 shrink-0 rounded-full object-cover" />
                    {{ row.name }}
                  </Link>
                </td>
                <td class="px-3 py-2.5 text-xs whitespace-nowrap text-muted-foreground">{{ formatDateTime(row.signed_up_at) }}</td>
                <td
                  v-for="cell in [
                    { at: row.welcomed_at, note: null },
                    { at: row.listing_at, note: sourceLabel(row.listing_from) },
                    { at: row.banner_at, note: null },
                    { at: row.product_at, note: productName(row.product_slug) },
                    { at: row.installed_at, note: productName(row.installed_slug) },
                  ]"
                  :key="`${row.id}-${cell.at}-${cell.note}`"
                  class="px-3 py-2.5 text-xs"
                >
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap" :title="cell.at !== null ? formatDateTime(cell.at) : undefined">
                    <span class="size-2 shrink-0 rounded-full" :class="cell.at !== null ? 'bg-cyan-400/70' : 'border border-muted-foreground/40'" />
                    <template v-if="cell.at !== null">
                      {{ after(row, cell.at) }}
                      <span v-if="cell.note" class="text-muted-foreground">{{ cell.note }}</span>
                    </template>
                  </span>
                </td>
                <td class="px-3 py-2.5 text-xs">
                  <span
                    class="inline-flex items-center gap-1.5 whitespace-nowrap"
                    :title="row.on_stream_at !== null ? formatDateTime(row.on_stream_at) : undefined"
                  >
                    <span
                      class="size-2 shrink-0 rounded-full"
                      :class="row.on_stream_at !== null ? 'bg-cyan-400' : 'border border-muted-foreground/40'"
                    />
                    <template v-if="row.on_stream_at !== null">{{ after(row, row.on_stream_at) }}</template>
                  </span>
                </td>
                <td class="px-3 py-2.5 text-right">
                  <span class="rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap" :class="toneClass[status(row).tone]">{{
                    status(row).label
                  }}</span>
                </td>
              </tr>
              <EmptyState v-if="rows.length === 0" :colspan="9" message="Nobody signed up in this window." />
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
