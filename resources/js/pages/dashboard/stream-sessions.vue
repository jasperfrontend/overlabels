<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
  TvMinimalPlay,
  Clock,
  Swords,
  PencilLine,
  Trophy,
  AlertTriangle,
  MessageSquareQuote,
  HandCoins,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import { computed, ref } from 'vue';
import { useSessionDataFormatter } from '@/composables/useSessionDataFormatter';

interface PollChoice {
  id: string;
  title: string;
  votes: number;
  channel_points_votes: number;
  bits_votes: number;
}

interface Poll {
  id: string;
  title: string | null;
  started_at: string | null;
  ended_at: string | null;
  status: string | null;
  choices: PollChoice[];
  winners: PollChoice[];
  total_votes: number;
  truly_finished: boolean;
  lifecycle: {
    has_begin: boolean;
    has_end_resolved: boolean;
    has_end_archived: boolean;
  };
}

interface HypeTrainContribution {
  user_id: string;
  user_login: string;
  user_name: string;
  type: string;
  total: number;
}

interface HypeTrain {
  id: string | null;
  level: number;
  total: number;
  type: string | null;
  started_at: string | null;
  ended_at: string | null;
  top_contributions: HypeTrainContribution[];
}

interface GoalProgress {
  type: string;
  start: number;
  end: number;
  delta: number;
  target: number;
  updates: number;
}

interface TitleHistoryEntry {
  at: string;
  title: string | null;
  category_name: string | null;
  category_id: string | null;
  language: string | null;
}

interface ResubMessage {
  name: string | null;
  tier: string | null;
  cumulative_months: number | null;
  streak_months: number | null;
  message: string | null;
  at: string;
}

interface Raid {
  from: string | null;
  from_login: string | null;
  viewers: number;
  at: string;
}

interface RewardBreakdown {
  title: string;
  count: number;
  cost_per: number;
  total_cost: number;
}

interface IncomeTotal {
  service: string;
  currency: string;
  count: number;
  total: number;
}

interface Donation {
  service: string;
  from_name: string | null;
  amount: number;
  currency: string | null;
  message: string | null;
  at: string;
}

interface StreamSession {
  session_id: number;
  started_at: string;
  ended_at: string | null;
  completed: boolean;
  duration_seconds: number | null;
  helix_stream_id: string | null;
  window: {
    start: string;
    end: string;
    pre_buffer_seconds: number;
    post_buffer_seconds: number;
    anchored_on_eventsub: { online: boolean; offline: boolean };
  };
  anchors: { stream_online_at: string | null; stream_offline_at: string | null };
  event_counts: Record<string, number>;
  stats: {
    follows: { count: number };
    new_subscribers: { count: number; by_tier: Record<string, number> };
    resubs: { count: number; total_cumulative_months: number; recent_messages: ResubMessage[] };
    gift_subs: { count: number; total_subs_gifted: number };
    raids_received: { count: number; total_viewers: number; raids: Raid[] };
    cheers: { count: number; total_bits: number };
    channel_point_redemptions: { count: number; total_cost: number; by_reward: RewardBreakdown[] };
    polls: Poll[];
    hype_trains: HypeTrain[];
    goals: GoalProgress[];
    title_history: TitleHistoryEntry[];
    income: { totals: IncomeTotal[]; count: number; donations: Donation[] };
  };
}

const props = defineProps<{ sessions: StreamSession[] }>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Stream Sessions', href: '/dashboard/stream-sessions' },
];

const { userLocale, formatDate, formatTime } = useSessionDataFormatter();

const TABS = [
  { key: 'overview', label: 'Overview' },
  { key: 'twitch', label: 'Twitch' },
  { key: 'income', label: 'Income' },
  { key: 'engagement', label: 'Engagement' },
  { key: 'raw', label: 'Raw' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

const selectedId = ref<number | null>(props.sessions[0]?.session_id ?? null);
const activeTab = ref<TabKey>('overview');

const selected = computed<StreamSession | null>(
  () => props.sessions.find((s) => s.session_id === selectedId.value) ?? null,
);

function select(id: number) {
  selectedId.value = id;
}

function fmtNum(n: number): string {
  return new Intl.NumberFormat(userLocale.value).format(n);
}

const decimalFmt = computed(
  () => new Intl.NumberFormat(userLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
);

function fmtCurrency(amount: number, currency: string | null): string {
  if (currency) {
    try {
      return new Intl.NumberFormat(userLocale.value, { style: 'currency', currency }).format(amount);
    } catch {
      // Unknown/invalid ISO code - fall back to a plain decimal plus the raw code.
    }
  }
  return currency ? `${decimalFmt.value.format(amount)} ${currency}` : decimalFmt.value.format(amount);
}

const SERVICE_LABELS: Record<string, string> = {
  twitch: 'Twitch',
  kofi: 'Ko-fi',
  streamlabs: 'StreamLabs',
  streamelements: 'StreamElements',
  bmac: 'Buy Me a Coffee',
  fourthwall: 'Fourthwall',
};

function serviceLabel(service: string): string {
  return SERVICE_LABELS[service] ?? service;
}

function fmtDurationFromSeconds(seconds: number | null): string | null {
  if (seconds === null) return null;
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
  return `${m}m ${String(s).padStart(2, '0')}s`;
}

/** Compact duration for the selector rail, e.g. "4h12m" / "47m". */
function fmtDurationCompact(seconds: number | null): string {
  if (seconds === null) return 'live';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (h > 0) return `${h}h${String(m).padStart(2, '0')}m`;
  return `${m}m`;
}

/** The headline number a streamer scans the rail for: follows + donation count. */
function railSummary(s: StreamSession): string {
  const parts: string[] = [`${fmtNum(s.stats.follows.count)} follows`];
  if (s.stats.income.count > 0) {
    parts.push(`${fmtNum(s.stats.income.count)} ${s.stats.income.count === 1 ? 'donation' : 'donations'}`);
  }
  return parts.join(' · ');
}

function tierLabel(tier: string | null): string {
  if (tier === '1000') return 'Tier 1';
  if (tier === '2000') return 'Tier 2';
  if (tier === '3000') return 'Tier 3';
  return tier ?? '';
}

function pollChoiceWidth(choice: PollChoice, total: number): string {
  if (total === 0) return '0%';
  return `${Math.round((choice.votes / total) * 100)}%`;
}

function isPollWinner(choice: PollChoice, winners: PollChoice[]): boolean {
  return winners.some((w) => w.id === choice.id);
}

function pollStatusVariant(status: string | null): 'default' | 'secondary' | 'destructive' {
  if (status === 'completed') return 'default';
  if (status === 'terminated') return 'destructive';
  return 'secondary';
}

function goalPercent(goal: GoalProgress): number {
  if (goal.target === 0) return 0;
  return Math.min(100, Math.round((goal.end / goal.target) * 100));
}

function goalLabel(type: string): string {
  if (type === 'follow') return 'Follower goal';
  if (type === 'subscription') return 'Subscriber goal';
  return type;
}

function latestTitleEntry(s: StreamSession): TitleHistoryEntry | null {
  return s.stats.title_history.at(-1) ?? null;
}

function formatTierBreakdown(byTier: Record<string, number>): string {
  const parts: string[] = [];
  if (byTier['1000']) parts.push(`T1 ${byTier['1000']}`);
  if (byTier['2000']) parts.push(`T2 ${byTier['2000']}`);
  if (byTier['3000']) parts.push(`T3 ${byTier['3000']}`);
  return parts.join(' · ');
}

function gifterLabel(count: number): string {
  return count === 1 ? 'from 1 gifter' : `from ${fmtNum(count)} gifters`;
}

function viewersLabel(count: number): string {
  return count === 1 ? '1 viewer' : `${fmtNum(count)} viewers`;
}

function monthsLabel(count: number): string {
  return count === 1 ? '1 cumulative month' : `${fmtNum(count)} cumulative months`;
}

/** Headline tiles for the Overview tab - flat, neutral, no invented accents. */
function headlineTiles(s: StreamSession): { label: string; value: string; sub?: string }[] {
  return [
    { label: 'Follows', value: fmtNum(s.stats.follows.count) },
    {
      label: 'New subs',
      value: fmtNum(s.stats.new_subscribers.count),
      sub: formatTierBreakdown(s.stats.new_subscribers.by_tier) || undefined,
    },
    {
      label: 'Resubs',
      value: fmtNum(s.stats.resubs.count),
      sub: s.stats.resubs.total_cumulative_months > 0 ? monthsLabel(s.stats.resubs.total_cumulative_months) : undefined,
    },
    {
      label: 'Gift subs',
      value: fmtNum(s.stats.gift_subs.total_subs_gifted),
      sub: s.stats.gift_subs.count > 0 ? gifterLabel(s.stats.gift_subs.count) : undefined,
    },
    {
      label: 'Raids in',
      value: fmtNum(s.stats.raids_received.count),
      sub: s.stats.raids_received.total_viewers > 0 ? viewersLabel(s.stats.raids_received.total_viewers) : undefined,
    },
    {
      label: 'Bits',
      value: fmtNum(s.stats.cheers.total_bits),
      sub: s.stats.cheers.count > 0 ? `${fmtNum(s.stats.cheers.count)} cheers` : undefined,
    },
    {
      label: 'Redemptions',
      value: fmtNum(s.stats.channel_point_redemptions.count),
      sub:
        s.stats.channel_point_redemptions.total_cost > 0
          ? `${fmtNum(s.stats.channel_point_redemptions.total_cost)} pts spent`
          : undefined,
    },
    { label: 'Donations', value: fmtNum(s.stats.income.count) },
  ];
}

function hasTwitchDetail(s: StreamSession): boolean {
  return (
    s.stats.title_history.length > 1 ||
    s.stats.raids_received.raids.length > 0 ||
    s.stats.cheers.count > 0 ||
    s.stats.resubs.recent_messages.length > 0 ||
    s.stats.channel_point_redemptions.by_reward.length > 0
  );
}

function hasEngagement(s: StreamSession): boolean {
  return s.stats.goals.length > 0 || s.stats.polls.length > 0 || s.stats.hype_trains.length > 0;
}

const sectionHeading = 'text-xs font-semibold uppercase tracking-wider text-foreground/60';
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Stream Sessions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
      <div class="flex items-center gap-3">
        <TvMinimalPlay class="h-6 w-6" />
        <Heading
          title="Stream Sessions"
          description="Your recent streams - every Twitch event and connected-service donation, one stream at a time."
          description-class="text-foreground"
        />
      </div>

      <div v-if="sessions.length === 0" class="text-foreground text-sm max-w-2xl">
        <p>No streams have been recorded yet. Once you go live, each stream's events will be aggregated here.</p>
      </div>

      <div v-else class="flex flex-col gap-4 lg:flex-row">
        <!-- Selector rail -->
        <nav
          class="shrink-0 border border-sidebar-border bg-card lg:w-72"
          aria-label="Streams"
        >
          <ul class="max-h-64 divide-y divide-sidebar-border overflow-y-auto lg:max-h-[72vh]">
            <li v-for="s in sessions" :key="s.session_id">
              <button
                type="button"
                class="flex w-full cursor-pointer flex-col gap-1 px-4 py-3 text-left transition-colors hover:bg-background/50"
                :class="s.session_id === selectedId ? 'bg-background/60 border-l-2 border-l-violet-400' : 'border-l-2 border-l-transparent'"
                @click="select(s.session_id)"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="font-medium text-foreground">{{ formatDate(s.started_at) }}</span>
                  <span class="text-xs text-foreground/70 tabular-nums">{{ fmtDurationCompact(s.duration_seconds) }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-foreground/70">
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    :class="s.completed ? 'bg-muted-foreground/40' : 'bg-green-500'"
                    :title="s.completed ? 'Ended' : 'Live'"
                  ></span>
                  <span class="truncate">{{ railSummary(s) }}</span>
                </div>
              </button>
            </li>
          </ul>
        </nav>

        <!-- Detail panel -->
        <section v-if="selected" class="min-w-0 flex-1 border border-sidebar-border bg-card">
          <!-- Header -->
          <header class="space-y-2 border-b border-sidebar-border p-4">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
              <span class="text-base font-semibold text-foreground">{{ formatDate(selected.started_at) }}</span>
              <span class="text-sm text-foreground">{{ formatTime(selected.started_at) }}</span>
              <span v-if="selected.ended_at" class="text-sm text-foreground/60">-</span>
              <span v-if="selected.ended_at" class="text-sm text-foreground">{{ formatTime(selected.ended_at) }}</span>
              <span v-if="selected.duration_seconds !== null" class="inline-flex items-center gap-1 text-sm text-foreground">
                <Clock class="h-3.5 w-3.5" />
                {{ fmtDurationFromSeconds(selected.duration_seconds) }}
              </span>
              <Badge v-if="selected.completed" variant="secondary">Ended</Badge>
              <Badge v-else variant="default">Live</Badge>
            </div>
            <div v-if="latestTitleEntry(selected)" class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
              <PencilLine class="h-3.5 w-3.5 self-center text-foreground/60 shrink-0" />
              <span class="font-medium text-foreground">{{ latestTitleEntry(selected)?.title }}</span>
              <span v-if="latestTitleEntry(selected)?.category_name" class="text-sm text-foreground/80">
                in {{ latestTitleEntry(selected)?.category_name }}
              </span>
            </div>
          </header>

          <!-- Tabs -->
          <div class="flex flex-wrap gap-1 border-b border-sidebar-border px-2 pt-2">
            <button
              v-for="tab in TABS"
              :key="tab.key"
              type="button"
              class="cursor-pointer border-b-2 px-3 py-2 text-sm font-medium transition-colors"
              :class="
                activeTab === tab.key
                  ? 'border-b-violet-400 text-foreground'
                  : 'border-b-transparent text-foreground/60 hover:text-foreground'
              "
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <!-- Tab panels -->
          <div class="p-4">
            <!-- Overview -->
            <div v-if="activeTab === 'overview'" class="space-y-4">
              <div
                v-if="!selected.window.anchored_on_eventsub.online || !selected.window.anchored_on_eventsub.offline"
                class="flex items-start gap-2 border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-foreground"
              >
                <AlertTriangle class="h-4 w-4 mt-0.5 text-amber-500 shrink-0" />
                <p>
                  Capture window is approximate for this session
                  ({{ !selected.window.anchored_on_eventsub.online ? 'no stream.online event found' : '' }}{{ !selected.window.anchored_on_eventsub.online && !selected.window.anchored_on_eventsub.offline ? ', ' : '' }}{{ !selected.window.anchored_on_eventsub.offline ? 'no stream.offline event found' : '' }}).
                  Stats fall back to the session's recorded start and end times.
                </p>
              </div>

              <div class="grid grid-cols-2 gap-px border border-sidebar-border bg-sidebar-border sm:grid-cols-4">
                <div v-for="tile in headlineTiles(selected)" :key="tile.label" class="bg-card p-4 space-y-1">
                  <p class="text-xs font-medium uppercase tracking-wide text-foreground/60">{{ tile.label }}</p>
                  <p class="text-2xl font-semibold text-foreground tabular-nums">{{ tile.value }}</p>
                  <p v-if="tile.sub" class="text-xs text-foreground/70">{{ tile.sub }}</p>
                </div>
              </div>
            </div>

            <!-- Twitch -->
            <div v-else-if="activeTab === 'twitch'" class="space-y-6">
              <p v-if="!hasTwitchDetail(selected)" class="text-sm text-foreground/60">
                No detailed Twitch activity recorded for this stream. Headline counts are on the Overview tab.
              </p>

              <!-- Subs by tier -->
              <div v-if="formatTierBreakdown(selected.stats.new_subscribers.by_tier)" class="space-y-2">
                <h3 :class="sectionHeading">New subscribers by tier</h3>
                <p class="text-sm text-foreground tabular-nums">{{ formatTierBreakdown(selected.stats.new_subscribers.by_tier) }}</p>
              </div>

              <!-- Resub messages -->
              <div v-if="selected.stats.resubs.recent_messages.length > 0" class="space-y-2">
                <h3 :class="sectionHeading">
                  <span class="inline-flex items-center gap-1.5">
                    <MessageSquareQuote class="h-3.5 w-3.5" /> Resub messages ({{ selected.stats.resubs.recent_messages.length }})
                  </span>
                </h3>
                <ul class="space-y-1.5">
                  <li
                    v-for="(msg, i) in selected.stats.resubs.recent_messages"
                    :key="i"
                    class="flex flex-col gap-1 border border-sidebar-border bg-background/40 p-2 text-sm sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
                  >
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 min-w-0">
                      <span class="font-medium text-foreground">{{ msg.name }}</span>
                      <Badge variant="secondary" class="text-xs">{{ tierLabel(msg.tier) }}</Badge>
                      <span v-if="msg.cumulative_months" class="text-xs text-foreground/80">
                        {{ msg.cumulative_months }} months<span v-if="msg.streak_months"> · {{ msg.streak_months }} streak</span>
                      </span>
                      <span v-if="msg.message" class="text-foreground italic min-w-0 truncate">"{{ msg.message }}"</span>
                    </div>
                    <span class="text-xs text-foreground/70 shrink-0 tabular-nums">{{ formatTime(msg.at) }}</span>
                  </li>
                </ul>
              </div>

              <!-- Cheers -->
              <div v-if="selected.stats.cheers.count > 0" class="space-y-2">
                <h3 :class="sectionHeading">Cheers</h3>
                <p class="text-sm text-foreground">
                  <span class="font-semibold tabular-nums">{{ fmtNum(selected.stats.cheers.count) }}</span> cheers ·
                  <span class="font-semibold tabular-nums">{{ fmtNum(selected.stats.cheers.total_bits) }}</span> bits
                </p>
              </div>

              <!-- Raids -->
              <div v-if="selected.stats.raids_received.raids.length > 0" class="space-y-2">
                <h3 :class="sectionHeading">
                  <span class="inline-flex items-center gap-1.5">
                    <Swords class="h-3.5 w-3.5" /> Incoming raids ({{ selected.stats.raids_received.raids.length }})
                  </span>
                </h3>
                <ul class="space-y-1">
                  <li
                    v-for="(raid, i) in selected.stats.raids_received.raids"
                    :key="i"
                    class="flex items-center justify-between border border-sidebar-border bg-background/40 p-2 text-sm"
                  >
                    <span class="font-medium text-foreground">{{ raid.from }}</span>
                    <span class="flex items-center gap-3">
                      <span class="text-foreground tabular-nums">{{ viewersLabel(raid.viewers) }}</span>
                      <span class="text-xs text-foreground/70">{{ formatTime(raid.at) }}</span>
                    </span>
                  </li>
                </ul>
              </div>

              <!-- Redemptions by reward -->
              <div v-if="selected.stats.channel_point_redemptions.by_reward.length > 0" class="space-y-2">
                <h3 :class="sectionHeading">Redemptions by reward</h3>
                <div class="overflow-x-auto border border-sidebar-border">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="border-b border-sidebar-border bg-background/40">
                        <th class="text-left p-2 font-medium text-foreground/80">Reward</th>
                        <th class="text-right p-2 font-medium text-foreground/80">Count</th>
                        <th class="text-right p-2 font-medium text-foreground/80">Cost</th>
                        <th class="text-right p-2 font-medium text-foreground/80">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="reward in selected.stats.channel_point_redemptions.by_reward"
                        :key="reward.title"
                        class="border-b border-sidebar-border last:border-0"
                      >
                        <td class="p-2 text-foreground">{{ reward.title }}</td>
                        <td class="p-2 text-right text-foreground tabular-nums">{{ fmtNum(reward.count) }}</td>
                        <td class="p-2 text-right text-foreground/80 tabular-nums">{{ fmtNum(reward.cost_per) }}</td>
                        <td class="p-2 text-right text-foreground tabular-nums font-medium">{{ fmtNum(reward.total_cost) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Title history -->
              <div v-if="selected.stats.title_history.length > 1" class="space-y-2">
                <h3 :class="sectionHeading">
                  <span class="inline-flex items-center gap-1.5">
                    <PencilLine class="h-3.5 w-3.5" /> Title and category changes ({{ selected.stats.title_history.length }})
                  </span>
                </h3>
                <ol class="space-y-2 border-l-2 border-sidebar-border pl-4">
                  <li v-for="(entry, i) in selected.stats.title_history" :key="i" class="space-y-0.5">
                    <p class="text-xs text-foreground/70">{{ formatTime(entry.at) }}</p>
                    <p class="text-sm text-foreground">{{ entry.title }}</p>
                    <p v-if="entry.category_name" class="text-xs text-foreground/80">{{ entry.category_name }}</p>
                  </li>
                </ol>
              </div>
            </div>

            <!-- Income -->
            <div v-else-if="activeTab === 'income'" class="space-y-6">
              <p v-if="selected.stats.income.count === 0" class="text-sm text-foreground/60">
                No donations from connected services were recorded for this stream. Ko-fi, StreamLabs, StreamElements,
                Buy Me a Coffee and Fourthwall donations show up here once they fire during a live stream.
              </p>

              <template v-else>
                <!-- Per-service / per-currency totals -->
                <div class="space-y-2">
                  <h3 :class="sectionHeading">
                    <span class="inline-flex items-center gap-1.5">
                      <HandCoins class="h-3.5 w-3.5" /> Totals
                    </span>
                  </h3>
                  <div class="overflow-x-auto border border-sidebar-border">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="border-b border-sidebar-border bg-background/40">
                          <th class="text-left p-2 font-medium text-foreground/80">Service</th>
                          <th class="text-right p-2 font-medium text-foreground/80">Count</th>
                          <th class="text-right p-2 font-medium text-foreground/80">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(row, i) in selected.stats.income.totals"
                          :key="i"
                          class="border-b border-sidebar-border last:border-0"
                        >
                          <td class="p-2 text-foreground">{{ serviceLabel(row.service) }}</td>
                          <td class="p-2 text-right text-foreground tabular-nums">{{ fmtNum(row.count) }}</td>
                          <td class="p-2 text-right text-foreground tabular-nums font-medium">{{ fmtCurrency(row.total, row.currency) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p class="text-xs text-foreground/60">Currencies are listed separately - no exchange-rate conversion.</p>
                </div>

                <!-- Individual donations -->
                <div v-if="selected.stats.income.donations.length > 0" class="space-y-2">
                  <h3 :class="sectionHeading">
                    Recent donations ({{ selected.stats.income.donations.length }})
                  </h3>
                  <ul class="space-y-1.5">
                    <li
                      v-for="(d, i) in selected.stats.income.donations"
                      :key="i"
                      class="flex flex-col gap-1 border border-sidebar-border bg-background/40 p-2 text-sm sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
                    >
                      <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 min-w-0">
                        <span class="font-medium text-foreground">{{ d.from_name ?? 'Anonymous' }}</span>
                        <span class="font-semibold text-foreground tabular-nums">{{ fmtCurrency(d.amount, d.currency) }}</span>
                        <Badge variant="secondary" class="text-xs">{{ serviceLabel(d.service) }}</Badge>
                        <span v-if="d.message" class="text-foreground italic min-w-0 truncate">"{{ d.message }}"</span>
                      </div>
                      <span class="text-xs text-foreground/70 shrink-0 tabular-nums">{{ formatTime(d.at) }}</span>
                    </li>
                  </ul>
                </div>
              </template>
            </div>

            <!-- Engagement -->
            <div v-else-if="activeTab === 'engagement'" class="space-y-6">
              <p v-if="!hasEngagement(selected)" class="text-sm text-foreground/60">
                No goals, polls or hype trains were recorded for this stream.
              </p>

              <!-- Goals -->
              <div v-if="selected.stats.goals.length > 0" class="space-y-2">
                <h3 :class="sectionHeading">Goals</h3>
                <div
                  v-for="goal in selected.stats.goals"
                  :key="goal.type"
                  class="border border-sidebar-border bg-background/40 p-3 space-y-2"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-medium text-foreground">{{ goalLabel(goal.type) }}</span>
                    <span class="text-foreground tabular-nums">
                      {{ fmtNum(goal.start) }} -> {{ fmtNum(goal.end) }}
                      <span class="text-foreground/70">/ {{ fmtNum(goal.target) }}</span>
                    </span>
                  </div>
                  <div class="h-2 rounded-sm bg-sidebar-accent overflow-hidden">
                    <div class="h-full bg-violet-400" :style="{ width: `${goalPercent(goal)}%` }" />
                  </div>
                  <p class="text-xs text-foreground/80">
                    {{ goalPercent(goal) }}% to target
                    <span v-if="goal.delta !== 0">· {{ goal.delta > 0 ? '+' : '' }}{{ goal.delta }} this stream</span>
                  </p>
                </div>
              </div>

              <!-- Polls -->
              <div v-if="selected.stats.polls.length > 0" class="space-y-2">
                <h3 :class="sectionHeading">Polls ({{ selected.stats.polls.length }})</h3>
                <div
                  v-for="poll in selected.stats.polls"
                  :key="poll.id"
                  class="border border-sidebar-border bg-background/40 p-3 space-y-2"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-foreground">{{ poll.title }}</p>
                    <div class="flex items-center gap-2">
                      <Badge :variant="pollStatusVariant(poll.status)" class="capitalize">{{ poll.status }}</Badge>
                      <span v-if="!poll.truly_finished" class="text-xs text-foreground/70">awaiting archive</span>
                    </div>
                  </div>
                  <p class="text-xs text-foreground/80">{{ fmtNum(poll.total_votes) }} total votes</p>
                  <ul class="space-y-1.5">
                    <li v-for="choice in poll.choices" :key="choice.id" class="space-y-0.5">
                      <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-foreground">
                          <Trophy v-if="isPollWinner(choice, poll.winners)" class="h-3.5 w-3.5 text-amber-400" />
                          {{ choice.title }}
                        </span>
                        <span class="text-foreground tabular-nums">{{ fmtNum(choice.votes) }}</span>
                      </div>
                      <div class="h-2 rounded-sm bg-sidebar-accent overflow-hidden">
                        <div
                          class="h-full"
                          :class="isPollWinner(choice, poll.winners) ? 'bg-amber-400' : 'bg-violet-400/70'"
                          :style="{ width: pollChoiceWidth(choice, poll.total_votes) }"
                        />
                      </div>
                    </li>
                  </ul>
                </div>
              </div>

              <!-- Hype trains -->
              <div v-if="selected.stats.hype_trains.length > 0" class="space-y-2">
                <h3 :class="sectionHeading">Hype trains ({{ selected.stats.hype_trains.length }})</h3>
                <div
                  v-for="(train, i) in selected.stats.hype_trains"
                  :key="train.id ?? i"
                  class="border border-sidebar-border bg-background/40 p-3 space-y-2"
                >
                  <div class="flex flex-wrap items-baseline gap-3 text-sm">
                    <span class="text-base font-semibold text-foreground">Level {{ train.level }}</span>
                    <span class="text-foreground/70">·</span>
                    <span class="text-foreground tabular-nums">{{ fmtNum(train.total) }} total</span>
                    <span v-if="train.type" class="text-foreground/70">·</span>
                    <span v-if="train.type" class="text-xs text-foreground/80 capitalize">{{ train.type }}</span>
                  </div>
                  <div v-if="train.top_contributions.length > 0" class="space-y-1">
                    <p class="text-xs text-foreground/80">Top contributors</p>
                    <ul class="space-y-0.5">
                      <li
                        v-for="(c, ci) in train.top_contributions"
                        :key="ci"
                        class="flex items-center justify-between text-sm"
                      >
                        <span class="text-foreground">{{ c.user_name }}</span>
                        <span class="text-foreground/80 tabular-nums">{{ fmtNum(c.total) }} {{ c.type }}</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <!-- Raw -->
            <div v-else-if="activeTab === 'raw'" class="space-y-4 text-sm text-foreground/80">
              <div class="space-y-1">
                <h3 :class="sectionHeading">Capture window</h3>
                <p>
                  {{ formatDate(selected.window.start) }} {{ formatTime(selected.window.start) }}
                  -> {{ formatDate(selected.window.end) }} {{ formatTime(selected.window.end) }}
                </p>
                <p>
                  Anchored on EventSub: online={{ selected.window.anchored_on_eventsub.online }},
                  offline={{ selected.window.anchored_on_eventsub.offline }}
                </p>
                <p v-if="selected.helix_stream_id">Helix stream id: {{ selected.helix_stream_id }}</p>
              </div>

              <div class="space-y-2">
                <h3 :class="sectionHeading">Event counts</h3>
                <div class="flex flex-wrap gap-1.5">
                  <Badge
                    v-for="(count, type) in selected.event_counts"
                    :key="type"
                    variant="secondary"
                    class="text-xs font-mono"
                  >
                    {{ type }} <span class="ml-1 text-foreground">{{ fmtNum(count) }}</span>
                  </Badge>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
