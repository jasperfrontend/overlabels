<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { AppPageProps, BreadcrumbItem, UsageSummary } from '@/types';

interface UsageMonth {
  period: string;
  broadcasts: number;
}

const props = defineProps<{
  usage: UsageSummary;
  history: UsageMonth[];
}>();

const page = usePage<AppPageProps>();
const locale = computed(() => page.props.auth.user.locale ?? 'en-US');

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Usage', href: '/settings/usage' },
];

function fmt(n: number): string {
  try {
    return new Intl.NumberFormat(locale.value).format(n);
  } catch {
    return String(n);
  }
}

function monthLabel(period: string): string {
  const [year, month] = period.split('-').map((v) => parseInt(v, 10));
  try {
    return new Intl.DateTimeFormat(locale.value, { month: 'short', year: 'numeric' }).format(
      new Date(year, month - 1, 1),
    );
  } catch {
    return period;
  }
}

const hasLimit = computed(() => props.usage.limit !== null && props.usage.limit > 0);

const percentUsed = computed(() => {
  if (!hasLimit.value) return 0;
  return Math.min(100, Math.round((props.usage.broadcasts / (props.usage.limit as number)) * 100));
});

const remaining = computed(() => {
  if (!hasLimit.value) return 0;
  return Math.max(0, (props.usage.limit as number) - props.usage.broadcasts);
});

const barColor = computed(() => {
  if (percentUsed.value >= 100) return 'bg-destructive';
  if (percentUsed.value >= 80) return 'bg-amber-500';
  return 'bg-primary';
});

// Scale the history bars against the busiest month so the strip is readable.
const historyPeak = computed(() => Math.max(1, ...props.history.map((m) => m.broadcasts)));
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Usage" />

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Events"
          description="Every inbound event you generate - a GPS ping, a donation, a Twitch follow/sub/cheer - counts as one. It is the single usage limit in Overlabels, and it is the same whether you run 1 overlay or 50. Everything else is free."
        />

        <div class="rounded-md border border-sidebar p-6">
          <div class="flex items-baseline justify-between gap-4">
            <div>
              <p class="text-3xl font-semibold text-foreground">{{ fmt(usage.broadcasts) }}</p>
              <p class="text-sm text-muted-foreground">events this month ({{ monthLabel(usage.period) }})</p>
            </div>
            <div v-if="hasLimit" class="text-right">
              <p class="text-sm text-foreground">{{ fmt(usage.limit as number) }} included</p>
              <p class="text-xs text-muted-foreground">{{ fmt(remaining) }} remaining</p>
            </div>
          </div>

          <template v-if="hasLimit">
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-muted">
              <div class="h-full rounded-full transition-all" :class="barColor" :style="{ width: percentUsed + '%' }" />
            </div>
            <p class="mt-2 text-xs text-muted-foreground">{{ percentUsed }}% of your monthly events used.</p>
          </template>

          <p v-else class="mt-4 text-sm text-foreground">
            No limit is being enforced yet. Overlabels is counting your events so a fair free-tier ceiling can be set
            from real usage. Your overlays will never be cut off without plenty of notice first.
          </p>
        </div>
      </div>

      <div class="space-y-6">
        <HeadingSmall title="Recent months" description="Your event count over the last six months. Counters reset on the 1st." />

        <div class="space-y-2">
          <div v-for="month in history" :key="month.period" class="flex items-center gap-3">
            <span class="w-24 shrink-0 text-xs text-muted-foreground">{{ monthLabel(month.period) }}</span>
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full bg-primary/60"
                :style="{ width: Math.round((month.broadcasts / historyPeak) * 100) + '%' }"
              />
            </div>
            <span class="w-16 shrink-0 text-right text-xs text-foreground">{{ fmt(month.broadcasts) }}</span>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
