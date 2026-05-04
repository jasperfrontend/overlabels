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
} from 'lucide-vue-next';
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

      <div class="space-y-4">
        <article
          v-for="session in sessions"
          :key="session.session_id"
          class="border border-sidebar-border bg-card p-4 space-y-4"
        >
          <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
              <span class="font-medium text-foreground">{{ formatDate(session.started_at) }}</span>
              <span class="text-sm text-muted-foreground">{{ formatTime(session.started_at) }}</span>
              <span v-if="session.ended_at" class="text-sm text-muted-foreground">-</span>
              <span v-if="session.ended_at" class="text-sm text-muted-foreground">{{ formatTime(session.ended_at) }}</span>
              <span v-if="session.duration_seconds !== null" class="inline-flex items-center gap-1 text-sm text-muted-foreground">
                <Clock class="h-3 w-3" /> {{ fmtDurationFromSeconds(session.duration_seconds) }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <Badge v-if="session.completed" variant="default" class="bg-green-400 hover:bg-green-400 text-primary-foreground">Completed</Badge>
              <Badge v-else variant="secondary" class="bg-amber-400 hover:bg-amber-400 text-primary-foreground">Live</Badge>
            </div>
          </header>

          <div
            v-if="!session.window.anchored_on_eventsub.online || !session.window.anchored_on_eventsub.offline"
            class="flex items-start gap-2 border border-sidebar-border bg-amber-500/10 p-3 text-sm text-foreground"
          >
            <AlertTriangle class="h-4 w-4 mt-0.5 text-amber-500 shrink-0" />
            <p>
              Capture window is approximate for this session
              ({{ !session.window.anchored_on_eventsub.online ? 'no stream.online event found' : '' }}{{ !session.window.anchored_on_eventsub.online && !session.window.anchored_on_eventsub.offline ? ', ' : '' }}{{ !session.window.anchored_on_eventsub.offline ? 'no stream.offline event found' : '' }}).
              Stats fall back to the session's recorded start and end times.
            </p>
          </div>

          <!-- Headline tiles -->
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <UserPlus class="h-3 w-3" /> Follows
              </div>
              <p class="text-lg font-semibold text-foreground">{{ fmtNum(session.stats.follows.count) }}</p>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Star class="h-3 w-3" /> New subs
              </div>
              <p class="text-lg font-semibold text-foreground">{{ fmtNum(session.stats.new_subscribers.count) }}</p>
              <p
                v-if="session.stats.new_subscribers.by_tier['1000'] || session.stats.new_subscribers.by_tier['2000'] || session.stats.new_subscribers.by_tier['3000']"
                class="text-xs text-muted-foreground"
              >
                T1 {{ session.stats.new_subscribers.by_tier['1000'] }} · T2 {{ session.stats.new_subscribers.by_tier['2000'] }} · T3 {{ session.stats.new_subscribers.by_tier['3000'] }}
              </p>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Repeat class="h-3 w-3" /> Resubs
              </div>
              <p class="text-lg font-semibold text-foreground">{{ fmtNum(session.stats.resubs.count) }}</p>
              <p v-if="session.stats.resubs.total_cumulative_months > 0" class="text-xs text-muted-foreground">
                {{ fmtNum(session.stats.resubs.total_cumulative_months) }} cumulative months
              </p>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Gift class="h-3 w-3" /> Gift subs
              </div>
              <p class="text-lg font-semibold text-foreground">{{ fmtNum(session.stats.gift_subs.total_subs_gifted) }}</p>
              <p v-if="session.stats.gift_subs.count > 0" class="text-xs text-muted-foreground">
                from {{ fmtNum(session.stats.gift_subs.count) }} gifters
              </p>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Swords class="h-3 w-3" /> Raids in
              </div>
              <p class="text-lg font-semibold text-foreground">{{ fmtNum(session.stats.raids_received.count) }}</p>
              <p v-if="session.stats.raids_received.total_viewers > 0" class="text-xs text-muted-foreground">
                {{ fmtNum(session.stats.raids_received.total_viewers) }} viewers
              </p>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Sparkles class="h-3 w-3" /> Redemptions
              </div>
              <p class="text-lg font-semibold text-foreground">{{ fmtNum(session.stats.channel_point_redemptions.count) }}</p>
              <p v-if="session.stats.channel_point_redemptions.total_cost > 0" class="text-xs text-muted-foreground">
                {{ fmtNum(session.stats.channel_point_redemptions.total_cost) }} pts spent
              </p>
            </div>
          </div>

          <!-- Latest title strip -->
          <div
            v-if="session.stats.title_history.length > 0"
            class="border border-sidebar-border bg-background/50 p-3"
          >
            <div class="flex items-center gap-1.5 text-xs text-muted-foreground mb-1">
              <PencilLine class="h-3 w-3" /> Stream title
            </div>
            <p class="text-sm text-foreground">{{ session.stats.title_history[session.stats.title_history.length - 1].title }}</p>
            <p class="text-xs text-muted-foreground">
              {{ session.stats.title_history[session.stats.title_history.length - 1].category_name }}
              <span v-if="session.stats.title_history.length > 1">· {{ session.stats.title_history.length }} title changes</span>
            </p>
          </div>

          <!-- Toggle -->
          <div>
            <button
              type="button"
              class="btn btn-plain btn-sm"
              @click="toggle(session.session_id)"
            >
              <component :is="expanded.has(session.session_id) ? ChevronUp : ChevronDown" class="h-3.5 w-3.5 mr-1.5" />
              {{ expanded.has(session.session_id) ? 'Hide details' : 'Show details' }}
            </button>
          </div>

          <!-- Details -->
          <section v-if="expanded.has(session.session_id)" class="space-y-6 pt-2">

            <!-- Title history -->
            <div v-if="session.stats.title_history.length > 0" class="space-y-2">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <PencilLine class="h-4 w-4" /> Title and category changes
              </h3>
              <ol class="space-y-2 border-l-2 border-sidebar-border pl-4">
                <li v-for="(entry, i) in session.stats.title_history" :key="i" class="space-y-0.5">
                  <p class="text-xs text-muted-foreground">{{ formatTime(entry.at) }}</p>
                  <p class="text-sm text-foreground">{{ entry.title }}</p>
                  <p v-if="entry.category_name" class="text-xs text-muted-foreground">{{ entry.category_name }}</p>
                </li>
              </ol>
            </div>

            <!-- Polls -->
            <div v-if="session.stats.polls.length > 0" class="space-y-3">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <BarChart3 class="h-4 w-4" /> Polls ({{ session.stats.polls.length }})
              </h3>
              <div
                v-for="poll in session.stats.polls"
                :key="poll.id"
                class="border border-sidebar-border p-3 space-y-2"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p class="text-sm font-medium text-foreground">{{ poll.title }}</p>
                  <div class="flex items-center gap-2">
                    <Badge :variant="pollStatusVariant(poll.status)" class="capitalize">{{ poll.status }}</Badge>
                    <span v-if="!poll.truly_finished" class="text-xs text-muted-foreground">awaiting archive</span>
                  </div>
                </div>
                <p class="text-xs text-muted-foreground">{{ fmtNum(poll.total_votes) }} total votes</p>
                <ul class="space-y-1.5">
                  <li v-for="choice in poll.choices" :key="choice.id" class="space-y-0.5">
                    <div class="flex items-center justify-between text-sm">
                      <span class="flex items-center gap-1.5 text-foreground">
                        <Trophy v-if="isPollWinner(choice, poll.winners)" class="h-3.5 w-3.5 text-amber-500" />
                        {{ choice.title }}
                      </span>
                      <span class="text-foreground tabular-nums">{{ fmtNum(choice.votes) }}</span>
                    </div>
                    <div class="h-2 rounded-sm bg-sidebar-accent overflow-hidden">
                      <div
                        class="h-full bg-violet-400/70"
                        :class="isPollWinner(choice, poll.winners) ? 'bg-amber-400' : 'bg-violet-400/70'"
                        :style="{ width: pollChoiceWidth(choice, poll.total_votes) }"
                      />
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Hype trains -->
            <div v-if="session.stats.hype_trains.length > 0" class="space-y-3">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <Flame class="h-4 w-4" /> Hype trains ({{ session.stats.hype_trains.length }})
              </h3>
              <div
                v-for="(train, i) in session.stats.hype_trains"
                :key="train.id ?? i"
                class="border border-sidebar-border p-3 space-y-2"
              >
                <div class="flex flex-wrap items-center gap-3 text-sm">
                  <span class="font-medium text-foreground">Level {{ train.level }}</span>
                  <span class="text-muted-foreground">·</span>
                  <span class="text-foreground">{{ fmtNum(train.total) }} contribution</span>
                  <span v-if="train.type" class="text-muted-foreground">·</span>
                  <span v-if="train.type" class="text-xs text-muted-foreground capitalize">{{ train.type }}</span>
                </div>
                <div v-if="train.top_contributions.length > 0" class="space-y-1">
                  <p class="text-xs text-muted-foreground">Top contributors</p>
                  <ul class="space-y-0.5">
                    <li
                      v-for="(c, ci) in train.top_contributions"
                      :key="ci"
                      class="flex items-center justify-between text-sm"
                    >
                      <span class="text-foreground">{{ c.user_name }}</span>
                      <span class="text-muted-foreground">{{ fmtNum(c.total) }} {{ c.type }}</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Goals -->
            <div v-if="session.stats.goals.length > 0" class="space-y-3">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <Target class="h-4 w-4" /> Goals
              </h3>
              <div
                v-for="goal in session.stats.goals"
                :key="goal.type"
                class="border border-sidebar-border p-3 space-y-2"
              >
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                  <span class="font-medium text-foreground">{{ goalLabel(goal.type) }}</span>
                  <span class="text-foreground tabular-nums">
                    {{ fmtNum(goal.start) }} -> {{ fmtNum(goal.end) }}
                    <span class="text-muted-foreground">/ {{ fmtNum(goal.target) }}</span>
                  </span>
                </div>
                <div class="h-2 rounded-sm bg-sidebar-accent overflow-hidden">
                  <div class="h-full bg-green-500/70" :style="{ width: `${goalPercent(goal)}%` }" />
                </div>
                <p class="text-xs text-muted-foreground">
                  {{ goalPercent(goal) }}% to target
                  <span v-if="goal.delta !== 0">· {{ goal.delta > 0 ? '+' : '' }}{{ goal.delta }} this stream</span>
                </p>
              </div>
            </div>

            <!-- Recent resubs -->
            <div v-if="session.stats.resubs.recent_messages.length > 0" class="space-y-3">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <MessageSquareQuote class="h-4 w-4" /> Recent resub messages ({{ session.stats.resubs.recent_messages.length }})
              </h3>
              <ul class="space-y-3">
                <li
                  v-for="(msg, i) in session.stats.resubs.recent_messages"
                  :key="i"
                  class="border border-sidebar-border p-3 space-y-1"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-sm">
                      <span class="font-medium text-foreground">{{ msg.name }}</span>
                      <Badge variant="secondary" class="text-xs">{{ tierLabel(msg.tier) }}</Badge>
                      <span v-if="msg.cumulative_months" class="text-xs text-muted-foreground">
                        {{ msg.cumulative_months }} months
                        <span v-if="msg.streak_months">({{ msg.streak_months }} streak)</span>
                      </span>
                    </div>
                    <span class="text-xs text-muted-foreground">{{ formatTime(msg.at) }}</span>
                  </div>
                  <p v-if="msg.message" class="text-sm text-foreground italic">"{{ msg.message }}"</p>
                </li>
              </ul>
            </div>

            <!-- Raids -->
            <div v-if="session.stats.raids_received.raids.length > 0" class="space-y-3">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <Swords class="h-4 w-4" /> Incoming raids ({{ session.stats.raids_received.raids.length }})
              </h3>
              <ul class="space-y-1">
                <li
                  v-for="(raid, i) in session.stats.raids_received.raids"
                  :key="i"
                  class="flex items-center justify-between border border-sidebar-border p-2 text-sm"
                >
                  <span class="text-foreground">{{ raid.from }}</span>
                  <span class="flex items-center gap-3">
                    <span class="text-foreground tabular-nums">{{ fmtNum(raid.viewers) }} viewers</span>
                    <span class="text-xs text-muted-foreground">{{ formatTime(raid.at) }}</span>
                  </span>
                </li>
              </ul>
            </div>

            <!-- Channel point redemptions by reward -->
            <div v-if="session.stats.channel_point_redemptions.by_reward.length > 0" class="space-y-3">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <Sparkles class="h-4 w-4" /> Redemptions by reward
              </h3>
              <div class="overflow-x-auto border border-sidebar-border">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-sidebar-border bg-background/50">
                      <th class="text-left p-2 font-medium text-muted-foreground">Reward</th>
                      <th class="text-right p-2 font-medium text-muted-foreground">Count</th>
                      <th class="text-right p-2 font-medium text-muted-foreground">Cost</th>
                      <th class="text-right p-2 font-medium text-muted-foreground">Total</th>
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
                      <td class="p-2 text-right text-muted-foreground tabular-nums">{{ fmtNum(reward.cost_per) }}</td>
                      <td class="p-2 text-right text-foreground tabular-nums">{{ fmtNum(reward.total_cost) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Cheers (only if any) -->
            <div v-if="session.stats.cheers.count > 0" class="space-y-2">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <Sparkles class="h-4 w-4" /> Cheers
              </h3>
              <p class="text-sm text-foreground">
                {{ fmtNum(session.stats.cheers.count) }} cheers ·
                <span class="text-foreground">{{ fmtNum(session.stats.cheers.total_bits) }} bits</span>
              </p>
            </div>

            <!-- Raw event counts -->
            <div class="space-y-2">
              <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                <Radio class="h-4 w-4" /> All event counts
              </h3>
              <div class="flex flex-wrap gap-1.5">
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

            <!-- Window detail (debug) -->
            <details class="text-xs text-muted-foreground">
              <summary class="cursor-pointer hover:text-foreground">Capture window</summary>
              <p class="mt-1">
                {{ formatDate(session.window.start) }} {{ formatTime(session.window.start) }}
                -> {{ formatDate(session.window.end) }} {{ formatTime(session.window.end) }}
              </p>
              <p>
                Anchored on EventSub: online={{ session.window.anchored_on_eventsub.online }},
                offline={{ session.window.anchored_on_eventsub.offline }}
              </p>
              <p v-if="session.helix_stream_id">Helix stream id: {{ session.helix_stream_id }}</p>
            </details>
          </section>
        </article>
      </div>
    </div>
  </AppLayout>
</template>
