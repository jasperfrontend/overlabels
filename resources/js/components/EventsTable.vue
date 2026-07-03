<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RefreshCw } from '@lucide/vue';
import { useEventColors } from '@/composables/useEventColors';
import type { UnifiedEvent } from '@/composables/useEventColors';

const { eventDotClass, eventHoverBorderClass } = useEventColors();

const props = defineProps<{
  events: UnifiedEvent[];
  // When set, replay posts to the token-authed /api endpoints instead of the
  // session-authed dashboard routes; used by the events feed, which has no
  // session and no Inertia. Requires the token's `write` ability server-side.
  token?: string;
}>();

const emit = defineEmits<{
  // Replay outcome for pages without Inertia flash messages (the events feed).
  'replay-result': [result: { message: string; type: string }];
}>();

const replayingId = ref<number | null>(null);
const confirmingId = ref<number | null>(null);

const getEventStatus = computed(() => (event: UnifiedEvent) => {
  const status = event?.event_data?.status;
  if (status === 'fulfilled') return { class: 'text-green-400', label: 'Complete' };
  if (status === 'unfulfilled') return { class: 'text-slate-400', label: 'Refunded' };
  return { class: 'hidden', label: '' };
});

function openConfirm(event: UnifiedEvent) {
  if (!canReplay(event) || replayingId.value === event.id) return;
  confirmingId.value = event.id;
}

function confirmAndReplay(event: UnifiedEvent) {
  confirmingId.value = null;
  replay(event);
}

const nonReplayableTypes = ['stream.online', 'stream.offline', 'channel.channel_points_custom_reward_redemption.update'];

function canReplay(event: UnifiedEvent): boolean {
  if (event.source !== 'twitch') return true;
  return !nonReplayableTypes.includes(event.event_type);
}

function replay(event: UnifiedEvent) {
  replayingId.value = event.id;

  if (props.token) {
    void replayViaToken(event, props.token);
    return;
  }

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

async function replayViaToken(event: UnifiedEvent, token: string) {
  const url = event.source === 'twitch'
    ? `/api/events/${event.id}/replay`
    : `/api/external-events/${event.id}/replay`;
  const fallback = { message: 'Could not replay the event. Check your connection and try again.', type: 'error' };
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ token }),
    });

    if (res.status === 403) {
      emit('replay-result', { message: 'This feed link is not allowed to replay alerts.', type: 'error' });
      return;
    }

    const json = await res.json().catch(() => null);
    if (json?.message) {
      emit('replay-result', { message: json.message, type: json.type ?? 'error' });
    } else if (json?.error) {
      emit('replay-result', { message: json.error, type: 'error' });
    } else {
      emit('replay-result', fallback);
    }
  } catch {
    emit('replay-result', fallback);
  } finally {
    replayingId.value = null;
  }
}

const externalEventLabels: Record<string, Record<string, string>> = {
  kofi: {
    donation: 'Ko tip-fi',
    subscription: 'Ko-fi subscription',
    shop_order: 'Ko-fi shop order',
    commission: 'Ko-fi commission',
  },
  streamlabs: {
    donation: 'Streamlabs tip',
    subscription: 'Streamlabs subscription',
    shop_order: 'Streamlabs shop order',
    commission: 'Streamlabs commission',
  },
  bmac: {
    donation: 'BMAC tip',
    recurring: 'BMAC subscription',
    extra: 'BMAC shop extra',
    membership: 'BMAC commission',
    wishlist: 'BMAC wishlist',
    commission: 'BMAC commission',
  },
  fourthwall: {
    donation: 'Fourthwall tip',
    subscription: 'Fourthwall subscription',
    shop_order: 'Fourthwall shop order',
    commission: 'Fourthwall commission',
  },
  streamelements: {
    donation: 'StreamElements tip',
  }
};

// Flat map - every Twitch event type resolves to exactly one human label.
// Keeping this next to externalEventLabels so both label sources are visible
// at a glance when adding new event types.
const twitchEventLabels: Record<string, string> = {
  'channel.follow': 'followed',
  'channel.subscribe': 'subscribed',
  'channel.subscription.message': 'resubscribed',
  'channel.subscription.gift': 'gifted subs',
  'channel.cheer': 'cheered',
  'channel.raid': 'raided',
  'channel.channel_points_custom_reward_redemption.add': 'redeemed',
  'channel.channel_points_custom_reward_redemption.update': 'redemption updated',
  'stream.online': 'went live',
  'stream.offline': 'ended the stream',

  // Polls
  'channel.poll.begin': 'Poll started',
  'channel.poll.progress': 'Poll updated',
  'channel.poll.end': 'Poll ended',

  // Hype train labels are computed dynamically from event data - see hypeTrainLabels()

  // Goals
  'channel.goal.begin': 'Goal started',
  'channel.goal.progress': 'Goal progressed',
  'channel.goal.end': 'Goal ended',
};

function label(event: UnifiedEvent): string {
  if (event.source === 'twitch') {
    if (event.event_type.startsWith('channel.hype_train.')) {
      return hypeTrainLabels(event) || event.event_type;
    }
    return twitchEventLabels[event.event_type] ?? event.label ?? event.event_type;
  }
  return externalEventLabels[event.source]?.[event.event_type] ?? `${event.source}: ${event.event_type}`;
}

function hypeTrainLabels(event: UnifiedEvent): string {
  if (
    event.event_type !== 'channel.hype_train.begin' &&
    event.event_type !== 'channel.hype_train.progress' &&
    event.event_type !== 'channel.hype_train.end'
  ) return '';
  const d = event.event_data as Record<string, unknown>;
  const total = d.total as number;
  const progress = d.progress as number;
  const goal = d.goal as number;
  const level = d.level as number;
  if (event.event_type === 'channel.hype_train.begin') {
    return `Hype Train started at level ${level}: ${progress} of ${goal}`;
  }
  if (event.event_type === 'channel.hype_train.progress') {
    return `Hype Train progressed to level ${level}: ${progress} of ${goal}`;
  }
  if (event.event_type === 'channel.hype_train.end') {
    const top = (d.top_contributions as Array<{ user_name: string; total: number; type: string; }> | undefined) ?? [];
    const contributors = top.map((c) => `${c.user_name}: ${c.total} ${c.type}`);
    const suffix = contributors.length
      ? `. Top contributions: ${new Intl.ListFormat(undefined, { type: 'conjunction' }).format(contributors)}`
      : '';
    return `Hype Train ended at level ${level}: ${total} contributions${suffix}.`;
  }
  return '';

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
    case 'channel.channel_points_custom_reward_redemption.update':
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


</script>

<template>
  <div class="flex flex-col gap-2 mt-4">
    <Popover
      v-for="event in events"
      :key="`${event.source}-${event.id}`"
      :open="confirmingId === event.id"
      @update:open="(open: boolean) => (confirmingId = open && canReplay(event) ? event.id : null)"
    >
      <PopoverTrigger as-child>
        <div
          :class="[
            'group flex items-start justify-between gap-4 flex-row overlabels-background',
            eventHoverBorderClass(event),
            canReplay(event) && confirmingId !== event.id ? 'cursor-pointer overlabels-background transition-all duration-100' : '',
            confirmingId !== null && confirmingId !== event.id ? 'opacity-30' : '',
            confirmingId === event.id ? 'border-violet-400 dark:border-violet-300' : '',

          ]"
          :role="canReplay(event) ? 'button' : undefined"
          :tabindex="canReplay(event) ? 0 : undefined"
          @click="canReplay(event) && confirmingId !== event.id ? openConfirm(event) : undefined"
          @keydown.enter.prevent="openConfirm(event)"
          @keydown.space.prevent="openConfirm(event)"
        >
          <div class="flex flex-col md:flex-row min-w-0 flex-1 gap-1 group text-sm" :id="label(event)">
            <div class="flex flex-nowrap items-center gap-x-2 gap-y-1 max-w-full">
              <div class="h-2 w-2 shrink-0 rounded-full" :class="eventDotClass(event)"></div>
              <span v-if="who(event)" class="font-bold">{{ who(event) }}</span>
              <div class="group-hover:text-foreground whitespace-nowrap overflow-x-hidden md:max-w-90 text-ellipsis">{{ label(event) }}</div>
            </div>
            <div class="flex items-center gap-2 pl-4 text-xs w-full">
              <div class="whitespace-nowrap text-ellipsis">{{ relativeTime(event.created_at) }}</div>
              <RefreshCw v-if="replayingId === event.id" class="h-3 w-3 animate-spin" />

              <div v-if="details(event)" class="whitespace-nowrap text-ellipsis">{{ details(event) }}</div>
            </div>
              <span :class="getEventStatus(event).class" class="text-xs ml-4">{{ getEventStatus(event).label }}</span>
          </div>
        </div>
      </PopoverTrigger>

      <PopoverContent class="w-auto p-3 bg-accent" side="top" :side-offset="-1" align="start">
        <div class="flex items-center gap-3">
          <span class="text-sm text-foreground">Replay &ldquo;{{ event.label }}&rdquo;?</span>
          <button :ref="(el: any) => el?.focus({ focusVisible: true })" class="btn btn-primary btn-xs" @click="confirmAndReplay(event)">Yes</button>
          <button class="btn btn-chill btn-xs" @click="confirmingId = null">Cancel</button>
        </div>
      </PopoverContent>
    </Popover>

  </div>
</template>
