<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Clock, RefreshCw } from 'lucide-vue-next';


interface UnifiedEvent {
  id: number;
  source: string; // 'twitch' | 'kofi' | etc.
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

defineProps<{
  events: UnifiedEvent[];
}>();

const replayingId = ref<number | null>(null);

const nonReplayableTypes = ['stream.online', 'stream.offline'];

function canReplay(event: UnifiedEvent): boolean {
  if (event.source !== 'twitch') return true;
  return !nonReplayableTypes.includes(event.event_type);
}

function replay(event: UnifiedEvent) {
  replayingId.value = event.id;
  const url = event.source === 'twitch'
    ? `/events/${event.id}/replay`
    : `/external-events/${event.id}/replay`;
  router.post(
    url,
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        replayingId.value = null;
      },
    },
  );
}

const twitchEventLabels: Record<string, string> = {
  'channel.follow': 'Followed',
  'channel.subscribe': 'Subscribed',
  'channel.subscription.gift': 'Gifted Sub',
  'channel.subscription.message': 'Resubbed',
  'channel.cheer': 'Cheered',
  'channel.raid': 'Raided',
  'channel.channel_points_custom_reward_redemption.add': 'Redeemed',
  'stream.online': 'Stream Online',
  'stream.offline': 'Stream Offline',
};

const externalEventLabels: Record<string, Record<string, string>> = {
  kofi: {
    donation: 'Donated through Ko-fi',
    subscription: 'Subscribed through Ko-fi',
    shop_order: 'Ordered something from the Ko-fi shop',
    commission: 'Ordered a Commission through Ko-fi',
  },
};

function label(event: UnifiedEvent): string {
  if (event.source === 'twitch') {
    return twitchEventLabels[event.event_type] ?? event.event_type;
  }
  return externalEventLabels[event.source]?.[event.event_type] ?? `${event.source}: ${event.event_type}`;
}

function who(event: UnifiedEvent): string | null {
  if (event.source !== 'twitch') {
    return (event.normalized_payload?.['event.from_name'] as string) ?? null;
  }
  const d = event.event_data ?? {};
  if (event.event_type === 'channel.raid') return (d.from_broadcaster_user_name as string) ?? null;
  if (event.event_type === 'stream.online' || event.event_type === 'stream.offline') return null;
  return (d.user_name as string) ?? null;
}

function details(event: UnifiedEvent): string | null {
  if (event.source !== 'twitch') {
    const p = event.normalized_payload;
    if (!p) return null;
    const amount = p['event.amount'] as string | undefined;
    const currency = p['event.currency'] as string | undefined;
    if (amount) return currency ? `${amount} ${currency}` : amount;
    const tier = p['event.tier_name'] as string | undefined;
    return tier ?? null;
  }
  const d = event.event_data ?? {};
  switch (event.event_type) {
    case 'channel.subscribe':
    case 'channel.subscription.message':
      return d.tier ? `Tier ${String(d.tier).replace('1000', '1').replace('2000', '2').replace('3000', '3')}` : null;
    case 'channel.subscription.gift':
      return d.total ? `${d.total} gifts` : null;
    case 'channel.cheer':
      return d.bits ? `${d.bits} bits` : null;
    case 'channel.raid':
      return d.viewers ? `${d.viewers} viewers` : null;
    case 'channel.channel_points_custom_reward_redemption.add':
      return ((d.reward as Record<string, unknown>)?.title as string) ?? null;
    default:
      return null;
  }
}

function relativeTime(iso: string): string {
  const diff = new Date(iso).getTime() - Date.now();
  const abs = Math.abs(diff);
  const minute = 60_000;
  const hour = 60 * minute;
  const day = 24 * hour;
  const week = 7 * day;
  const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  if (abs < minute) return 'just now';
  if (abs < hour) return rtf.format(Math.round(diff / minute), 'minute');
  if (abs < day) return rtf.format(Math.round(diff / hour), 'hour');
  if (abs < week) return rtf.format(Math.round(diff / day), 'day');
  return rtf.format(Math.round(diff / week), 'week');
}

function eventColor(event: UnifiedEvent): any {
  const type = event.event_type;
  if (type === 'channel.subscribe') return 'bg-purple-500';
  if (type === 'channel.subscription.gift') return 'bg-pink-500';
  if (type === 'channel.subscription.message') return 'bg-indigo-500';
  if (type === 'channel.raid') return 'bg-rose-500';
  if (type === 'channel.cheer') return 'bg-amber-500';
  if (type === 'stream.online') return 'bg-green-500';
  if (type === 'stream.offline') return 'bg-red-500';
  if (type === 'channel.channel_points_custom_reward_redemption.add') return 'bg-cyan-500';
  if (type === 'channel.follow') return 'bg-green-500';
  return 'bg-slate-500';
}

</script>

<template>
  <div class="flex flex-col gap-2">
    <div
      v-for="event in events"
      :key="`${event.source}-${event.id}`"
      class="group flex items-start justify-between gap-3 rounded-lg border bg-card p-3 transition-colors hover:bg-accent/50"
    >
      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <div class="h-2 w-2 shrink-0 rounded-full" :class="eventColor(event)"></div>
          <span v-if="who(event)" class="font-bold">{{ who(event) }}</span>
          <span v-else class="italic text-muted-foreground/50">-</span>
          <span class="text-muted-foreground">{{ label(event) }}</span>
          <span v-if="details(event)">{{ details(event) }}</span>
        </div>
        <div class="flex items-center gap-2 pl-4 text-xs text-muted-foreground/60">
          <Clock class="h-3 w-3" />
          <span>{{ relativeTime(event.created_at) }}</span>
        </div>
      </div>

      <button
        v-if="canReplay(event)"
        :disabled="replayingId === event.id"
        class="shrink-0 rounded p-2 opacity-40 transition-opacity hover:opacity-100 group-hover:opacity-80"
        @click="replay(event)"
      >
        <RefreshCw class="h-4 w-4 cursor-pointer" :class="{ 'animate-spin': replayingId === event.id }" />
      </button>
    </div>
  </div>
</template>
