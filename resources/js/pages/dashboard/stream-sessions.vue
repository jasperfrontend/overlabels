<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
  TvMinimalPlay,
  Clock,
  UserPlus,
  Star,
  Repeat,
  Gift,
  Swords,
  Sparkles,
  BarChart3,
  Flame,
  Target,
  PencilLine,
  Trophy,
  ChevronDown,
  ChevronUp,
  AlertTriangle,
  Radio,
  MessageSquareQuote,
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';
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
  };
}

defineProps<{ sessions: StreamSession[] }>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Stream Sessions', href: '/dashboard/stream-sessions' },
];

const { userLocale, formatDate, formatTime } = useSessionDataFormatter();

const expanded = ref<Set<number>>(new Set());

function toggle(id: number) {
  const next = new Set(expanded.value);
  if (next.has(id)) next.delete(id); else next.add(id);
  expanded.value = next;
}

function fmtNum(n: number): string {
  return new Intl.NumberFormat(userLocale.value).format(n);
}

function fmtDurationFromSeconds(seconds: number | null): string | null {
  if (seconds === null) return null;
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
  return `${m}m ${String(s).padStart(2, '0')}s`;
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
  return winners.some(w => w.id === choice.id);
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

function hasAudienceContent(s: StreamSession): boolean {
  return s.stats.title_history.length > 1
    || s.stats.raids_received.raids.length > 0;
}

function hasMonetizationContent(s: StreamSession): boolean {
  return s.stats.cheers.count > 0
    || s.stats.resubs.recent_messages.length > 0
    || s.stats.channel_point_redemptions.by_reward.length > 0;
}

function hasEngagementContent(s: StreamSession): boolean {
  return s.stats.goals.length > 0
    || s.stats.polls.length > 0
    || s.stats.hype_trains.length > 0;
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Stream Sessions" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
      <div class="flex items-center gap-3">
        <TvMinimalPlay class="h-6 w-6" />
        <Heading
          title="Stream Sessions"
          description="A per-stream overview of follows, subs, raids, polls, hype trains and more."
          description-class="text-foreground"
        />
      </div>

      <div v-if="sessions.length === 0" class="text-foreground text-sm max-w-2xl">
        <p>No streams have been recorded yet. Once you go live, each stream's events will be aggregated here.</p>
      </div>

      <div class="space-y-6">
        <article
          v-for="session in sessions"
          :key="session.session_id"
          class="border border-sidebar-border bg-card overflow-hidden"
        >
          <!-- Header -->
          <header class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2 min-w-0 flex-1">
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-base font-semibold text-foreground">{{ formatDate(session.started_at) }}</span>
                <span class="text-sm text-foreground/60">·</span>
                <span class="text-sm text-foreground">{{ formatTime(session.started_at) }}</span>
                <span v-if="session.ended_at" class="text-sm text-foreground/60">-</span>
                <span v-if="session.ended_at" class="text-sm text-foreground">{{ formatTime(session.ended_at) }}</span>
                <span v-if="session.duration_seconds !== null" class="inline-flex items-center gap-1 text-sm text-foreground">
                  <Clock class="h-3.5 w-3.5" />
                  {{ fmtDurationFromSeconds(session.duration_seconds) }}
                </span>
              </div>

              <div v-if="latestTitleEntry(session)" class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <PencilLine class="h-3.5 w-3.5 self-center text-foreground/60 shrink-0" />
                <span class="font-medium text-foreground">{{ latestTitleEntry(session)?.title }}</span>
                <span v-if="latestTitleEntry(session)?.category_name" class="text-sm text-foreground/80">in {{ latestTitleEntry(session)?.category_name }}</span>
                <span v-if="session.stats.title_history.length > 1" class="text-xs text-foreground/60">· {{ session.stats.title_history.length }} title changes</span>
              </div>
            </div>

            <div class="shrink-0">
              <Badge v-if="session.completed" variant="default" class="bg-green-400 hover:bg-green-400 text-primary-foreground">Completed</Badge>
              <Badge v-else variant="secondary" class="bg-amber-400 hover:bg-amber-400 text-primary-foreground">Live</Badge>
            </div>
          </header>

          <!-- Anchor warning -->
          <div
            v-if="!session.window.anchored_on_eventsub.online || !session.window.anchored_on_eventsub.offline"
            class="mx-4 mb-4 flex items-start gap-2 border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-foreground"
          >
            <AlertTriangle class="h-4 w-4 mt-0.5 text-amber-500 shrink-0" />
            <p>
              Capture window is approximate for this session
              ({{ !session.window.anchored_on_eventsub.online ? 'no stream.online event found' : '' }}{{ !session.window.anchored_on_eventsub.online && !session.window.anchored_on_eventsub.offline ? ', ' : '' }}{{ !session.window.anchored_on_eventsub.offline ? 'no stream.offline event found' : '' }}).
              Stats fall back to the session's recorded start and end times.
            </p>
          </div>

          <!-- Hero tiles -->
          <div class="grid grid-cols-2 gap-3 px-4 pb-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="border border-sidebar-border border-t-2 border-t-violet-400 bg-background/40 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <UserPlus class="h-4 w-4 text-violet-400" />
                <span class="text-sm font-medium text-foreground">Follows</span>
              </div>
              <p class="text-3xl font-bold text-foreground tabular-nums">{{ fmtNum(session.stats.follows.count) }}</p>
            </div>

            <div class="border border-sidebar-border border-t-2 border-t-amber-400 bg-background/40 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <Star class="h-4 w-4 text-amber-400" />
                <span class="text-sm font-medium text-foreground">New subs</span>
              </div>
              <p class="text-3xl font-bold text-foreground tabular-nums">{{ fmtNum(session.stats.new_subscribers.count) }}</p>
              <p v-if="formatTierBreakdown(session.stats.new_subscribers.by_tier)" class="text-xs text-foreground/80">
                {{ formatTierBreakdown(session.stats.new_subscribers.by_tier) }}
              </p>
            </div>

            <div class="border border-sidebar-border border-t-2 border-t-cyan-400 bg-background/40 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <Repeat class="h-4 w-4 text-cyan-400" />
                <span class="text-sm font-medium text-foreground">Resubs</span>
              </div>
              <p class="text-3xl font-bold text-foreground tabular-nums">{{ fmtNum(session.stats.resubs.count) }}</p>
              <p v-if="session.stats.resubs.total_cumulative_months > 0" class="text-xs text-foreground/80">
                {{ monthsLabel(session.stats.resubs.total_cumulative_months) }}
              </p>
            </div>

            <div class="border border-sidebar-border border-t-2 border-t-rose-400 bg-background/40 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <Gift class="h-4 w-4 text-rose-400" />
                <span class="text-sm font-medium text-foreground">Gift subs</span>
              </div>
              <p class="text-3xl font-bold text-foreground tabular-nums">{{ fmtNum(session.stats.gift_subs.total_subs_gifted) }}</p>
              <p v-if="session.stats.gift_subs.count > 0" class="text-xs text-foreground/80">
                {{ gifterLabel(session.stats.gift_subs.count) }}
              </p>
            </div>

            <div class="border border-sidebar-border border-t-2 border-t-red-400 bg-background/40 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <Swords class="h-4 w-4 text-red-400" />
                <span class="text-sm font-medium text-foreground">Raids in</span>
              </div>
              <p class="text-3xl font-bold text-foreground tabular-nums">{{ fmtNum(session.stats.raids_received.count) }}</p>
              <p v-if="session.stats.raids_received.total_viewers > 0" class="text-xs text-foreground/80">
                {{ viewersLabel(session.stats.raids_received.total_viewers) }}
              </p>
            </div>

            <div class="border border-sidebar-border border-t-2 border-t-emerald-400 bg-background/40 p-4 space-y-3">
              <div class="flex items-center gap-2">
                <Sparkles class="h-4 w-4 text-emerald-400" />
                <span class="text-sm font-medium text-foreground">Redemptions</span>
              </div>
              <p class="text-3xl font-bold text-foreground tabular-nums">{{ fmtNum(session.stats.channel_point_redemptions.count) }}</p>
              <p v-if="session.stats.channel_point_redemptions.total_cost > 0" class="text-xs text-foreground/80">
                {{ fmtNum(session.stats.channel_point_redemptions.total_cost) }} pts spent
              </p>
            </div>
          </div>

          <!-- Toggle -->
          <div class="border-t border-sidebar-border px-4 py-3">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 text-sm font-medium text-foreground hover:text-violet-400 cursor-pointer transition-colors"
              @click="toggle(session.session_id)"
            >
              <component :is="expanded.has(session.session_id) ? ChevronUp : ChevronDown" class="h-4 w-4" />
              {{ expanded.has(session.session_id) ? 'Hide details' : 'Show details' }}
            </button>
          </div>

          <!-- Details -->
          <section v-if="expanded.has(session.session_id)" class="border-t border-sidebar-border bg-background/20 p-4 space-y-4">

            <!-- Audience block -->
            <div
              v-if="hasAudienceContent(session)"
              class="border border-sidebar-border border-t-2 border-t-red-400 bg-card p-4 space-y-4"
            >
              <h3 class="text-xs font-semibold uppercase tracking-wider text-red-400">Audience</h3>

              <div v-if="session.stats.title_history.length > 1" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <PencilLine class="h-4 w-4" /> Title and category changes ({{ session.stats.title_history.length }})
                </h4>
                <ol class="space-y-2 border-l-2 border-sidebar-border pl-4">
                  <li v-for="(entry, i) in session.stats.title_history" :key="i" class="space-y-0.5">
                    <p class="text-xs text-foreground/70">{{ formatTime(entry.at) }}</p>
                    <p class="text-sm text-foreground">{{ entry.title }}</p>
                    <p v-if="entry.category_name" class="text-xs text-foreground/80">{{ entry.category_name }}</p>
                  </li>
                </ol>
              </div>

              <div v-if="session.stats.raids_received.raids.length > 0" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <Swords class="h-4 w-4" /> Incoming raids ({{ session.stats.raids_received.raids.length }})
                </h4>
                <ul class="space-y-1">
                  <li
                    v-for="(raid, i) in session.stats.raids_received.raids"
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
            </div>

            <!-- Monetization block -->
            <div
              v-if="hasMonetizationContent(session)"
              class="border border-sidebar-border border-t-2 border-t-amber-400 bg-card p-4 space-y-4"
            >
              <h3 class="text-xs font-semibold uppercase tracking-wider text-amber-400">Monetization</h3>

              <div v-if="session.stats.cheers.count > 0" class="flex items-center gap-2 text-sm">
                <Sparkles class="h-4 w-4 text-amber-400" />
                <span class="text-foreground">
                  <span class="font-semibold tabular-nums">{{ fmtNum(session.stats.cheers.count) }}</span> cheers
                  · <span class="font-semibold tabular-nums">{{ fmtNum(session.stats.cheers.total_bits) }}</span> bits
                </span>
              </div>

              <div v-if="session.stats.resubs.recent_messages.length > 0" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <MessageSquareQuote class="h-4 w-4" /> Recent resub messages ({{ session.stats.resubs.recent_messages.length }})
                </h4>
                <ul class="space-y-1.5">
                  <li
                    v-for="(msg, i) in session.stats.resubs.recent_messages"
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

              <div v-if="session.stats.channel_point_redemptions.by_reward.length > 0" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <Sparkles class="h-4 w-4" /> Redemptions by reward
                </h4>
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
                        v-for="reward in session.stats.channel_point_redemptions.by_reward"
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
            </div>

            <!-- Engagement block -->
            <div
              v-if="hasEngagementContent(session)"
              class="border border-sidebar-border border-t-2 border-t-cyan-400 bg-card p-4 space-y-4"
            >
              <h3 class="text-xs font-semibold uppercase tracking-wider text-cyan-400">Engagement</h3>

              <div v-if="session.stats.goals.length > 0" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <Target class="h-4 w-4" /> Goals
                </h4>
                <div
                  v-for="goal in session.stats.goals"
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
                    <div class="h-full bg-green-500/70" :style="{ width: `${goalPercent(goal)}%` }" />
                  </div>
                  <p class="text-xs text-foreground/80">
                    {{ goalPercent(goal) }}% to target
                    <span v-if="goal.delta !== 0">· {{ goal.delta > 0 ? '+' : '' }}{{ goal.delta }} this stream</span>
                  </p>
                </div>
              </div>

              <div v-if="session.stats.polls.length > 0" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <BarChart3 class="h-4 w-4" /> Polls ({{ session.stats.polls.length }})
                </h4>
                <div
                  v-for="poll in session.stats.polls"
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

              <div v-if="session.stats.hype_trains.length > 0" class="space-y-2">
                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <Flame class="h-4 w-4" /> Hype trains ({{ session.stats.hype_trains.length }})
                </h4>
                <div
                  v-for="(train, i) in session.stats.hype_trains"
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

            <!-- Debug fold -->
            <details class="text-xs text-foreground/70">
              <summary class="cursor-pointer hover:text-foreground inline-flex items-center gap-1">
                <Radio class="h-3 w-3" /> Debug · capture window and event counts
              </summary>
              <div class="mt-2 space-y-2">
                <p>
                  Capture window: {{ formatDate(session.window.start) }} {{ formatTime(session.window.start) }}
                  -> {{ formatDate(session.window.end) }} {{ formatTime(session.window.end) }}
                </p>
                <p>
                  Anchored on EventSub: online={{ session.window.anchored_on_eventsub.online }},
                  offline={{ session.window.anchored_on_eventsub.offline }}
                </p>
                <p v-if="session.helix_stream_id">Helix stream id: {{ session.helix_stream_id }}</p>
                <div class="flex flex-wrap gap-1.5 pt-2">
                  <Badge
                    v-for="(count, type) in session.event_counts"
                    :key="type"
                    variant="secondary"
                    class="text-xs font-mono"
                  >
                    {{ type }} <span class="ml-1 text-foreground">{{ fmtNum(count) }}</span>
                  </Badge>
                </div>
              </div>
            </details>
          </section>
        </article>
      </div>
    </div>
  </AppLayout>
</template>
