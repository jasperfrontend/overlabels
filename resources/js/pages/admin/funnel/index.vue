<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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
}

interface Totals {
  signed_up: number;
  welcomed: number;
  listing: number;
  banner: number;
  dialog: number;
  product: number;
  installed: number;
}

const props = defineProps<{
  rows: FunnelRow[];
  totals: Totals;
  days: number;
  windows: number[];
  limited: boolean;
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Funnel', href: route('admin.funnel.index') },
];

// The steps in the order a new account walks them. The two sub-steps say
// which welcome link carried people to the listing.
const steps = computed(() => [
  { label: 'Signed up', count: props.totals.signed_up, sub: false },
  { label: 'Saw the welcome dialog', count: props.totals.welcomed, sub: false },
  { label: 'Reached the products page', count: props.totals.listing, sub: false },
  { label: 'clicked the banner', count: props.totals.banner, sub: true },
  { label: 'clicked the dialog button', count: props.totals.dialog, sub: true },
  { label: 'Opened a product page', count: props.totals.product, sub: false },
  { label: 'Installed a product', count: props.totals.installed, sub: false },
]);

function percent(count: number): string {
  if (props.totals.signed_up === 0) return '-';
  return `${Math.round((count / props.totals.signed_up) * 100)}%`;
}

function windowLabel(days: number): string {
  return days === 0 ? 'All time' : `${days} days`;
}

function formatDate(seconds: number): string {
  return new Date(seconds * 1000).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

// How long after signup a step happened, as one short unit.
function after(row: FunnelRow, seconds: number | null): string | null {
  if (seconds === null) return null;
  const delta = Math.max(0, seconds - row.signed_up_at);
  if (delta < 60) return `+${delta}s`;
  if (delta < 3600) return `+${Math.round(delta / 60)}m`;
  if (delta < 86400) return `+${Math.round(delta / 3600)}h`;
  return `+${Math.round(delta / 86400)}d`;
}

function sourceLabel(from: string | null): string | null {
  if (from === 'welcome-banner') return 'via banner';
  if (from === 'welcome-dialog') return 'via dialog';
  if (from === 'direct') return 'other link';
  return null;
}

// The furthest step reached, for the one-word status on small screens.
function furthest(row: FunnelRow): string {
  if (row.installed_at !== null) return 'Installed';
  if (row.product_at !== null) return 'Opened a product';
  if (row.listing_at !== null) return 'Reached products';
  if (row.welcomed_at !== null) return 'Saw the dialog';
  return 'Signed up';
}
</script>

<template>
  <Head><title>Admin - Funnel</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Welcome funnel" title-class="text-2xl font-bold">
        <template #actions>
          <div class="flex flex-wrap gap-1">
            <Link
              v-for="w in windows"
              :key="w"
              :href="route('admin.funnel.index', { days: w })"
              preserve-scroll
              class="cursor-pointer rounded border px-3 py-1.5 text-sm"
              :class="w === days ? 'border-foreground bg-foreground text-background' : 'border-sidebar hover:bg-card'"
            >
              {{ windowLabel(w) }}
            </Link>
          </div>
        </template>
      </PageHeader>

      <p class="max-w-[75ch] text-sm text-foreground">
        Accounts that signed up in this window, and the first time each one reached a step. Products page, product page and welcome-link clicks are
        recorded from October 23rd 2026 on, so accounts older than that show blanks there even if they got further.
      </p>

      <!-- Totals per step -->
      <div class="rounded border border-sidebar">
        <div
          v-for="step in steps"
          :key="step.label"
          class="flex items-center gap-3 border-t border-sidebar px-3 py-2 text-sm first:border-t-0"
          :class="step.sub ? 'pl-8 text-muted-foreground' : 'font-medium'"
        >
          <span class="min-w-0 flex-1">{{ step.label }}</span>
          <span class="w-10 text-right tabular-nums">{{ step.count }}</span>
          <span class="w-12 text-right text-xs text-muted-foreground tabular-nums">{{ percent(step.count) }}</span>
          <div class="hidden h-2 w-40 bg-card sm:block">
            <div class="h-2 bg-cyan-400" :style="{ width: totals.signed_up ? `${(step.count / totals.signed_up) * 100}%` : '0%' }" />
          </div>
        </div>
      </div>

      <p v-if="limited" class="text-sm text-muted-foreground">Showing the newest {{ rows.length }} accounts only.</p>

      <!-- Card view (< lg) -->
      <div class="space-y-2 lg:hidden">
        <EmptyState v-if="rows.length === 0" message="Nobody signed up in this window." />
        <div v-for="row in rows" :key="`card-${row.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <Link :href="route('admin.users.show', row.id)" class="cursor-pointer font-medium hover:underline">{{ row.name }}</Link>
            <span class="shrink-0 text-xs font-medium">{{ furthest(row) }}</span>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>Signed up {{ formatDate(row.signed_up_at) }}</span>
            <span v-if="row.listing_at !== null">Products {{ after(row, row.listing_at) }} {{ sourceLabel(row.listing_from) }}</span>
            <span v-if="row.product_slug">Opened {{ row.product_slug }}</span>
            <span v-if="row.installed_at !== null">Installed {{ row.installed_slug ?? '' }} {{ after(row, row.installed_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Table (>= lg) -->
      <div class="hidden overflow-x-auto rounded border border-sidebar lg:block">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Account</th>
              <th class="px-3 py-2">Signed up</th>
              <th class="px-3 py-2">Dialog</th>
              <th class="px-3 py-2">Products page</th>
              <th class="px-3 py-2">Banner</th>
              <th class="px-3 py-2">Product page</th>
              <th class="px-3 py-2">Installed</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-t border-sidebar">
              <td class="px-3 py-2">
                <Link :href="route('admin.users.show', row.id)" class="flex cursor-pointer items-center gap-2 font-medium hover:underline">
                  <img v-if="row.avatar" :src="row.avatar" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover" />
                  {{ row.name }}
                </Link>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ formatDate(row.signed_up_at) }}</td>
              <td class="px-3 py-2 text-xs">{{ after(row, row.welcomed_at) ?? '-' }}</td>
              <td class="px-3 py-2 text-xs">
                <template v-if="row.listing_at !== null">
                  {{ after(row, row.listing_at) }}
                  <span class="text-muted-foreground">{{ sourceLabel(row.listing_from) }}</span>
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-3 py-2 text-xs">{{ after(row, row.banner_at) ?? '-' }}</td>
              <td class="px-3 py-2 text-xs">
                <template v-if="row.product_at !== null">
                  {{ after(row, row.product_at) }}
                  <span class="text-muted-foreground">{{ row.product_slug }}</span>
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-3 py-2 text-xs">
                <template v-if="row.installed_at !== null">
                  {{ after(row, row.installed_at) }}
                  <span class="text-muted-foreground">{{ row.installed_slug }}</span>
                </template>
                <template v-else>-</template>
              </td>
            </tr>
            <EmptyState v-if="rows.length === 0" :colspan="7" message="Nobody signed up in this window." />
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
