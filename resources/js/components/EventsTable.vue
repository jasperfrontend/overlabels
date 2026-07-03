<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RefreshCw, ChevronDown, ChevronRight, Gift } from '@lucide/vue';
import { useEventColors } from '@/composables/useEventColors';
import type { UnifiedEvent } from '@/composables/useEventColors';
import ProviderIcon from '@/components/ProviderIcon.vue';

const { eventHoverBorderClass, eventDotClass } = useEventColors();

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
const expandedGifts = ref<Set<number>>(new Set());

// Twitch delivers a gift-sub bomb as one `channel.subscription.gift` event
// (the gifter, carrying `total`) plus N separate `channel.subscribe` events
// with `is_gift: true` (the recipients). Twitch does not link the recipient
// events back to the gifter, so we fold them together heuristically: for each
// gift event, claim the next `total` recipient events (in chronological order)
// that share the same broadcaster and tier. Everything else passes through
// untouched. Grouping is display-only - the underlying events, replay routes
// and pagination are all unchanged.
interface DisplayRow {
  event: UnifiedEvent;
  recipients: UnifiedEvent[];
}

const GIFT_EVENT = 'channel.subscription.gift';
const SUB_EVENT = 'channel.subscribe';
const RESUB_EVENT = 'channel.subscription.message';

// A resub fires BOTH `channel.subscribe` and `channel.subscription.message`
// for the same user at essentially the same instant, so the feed shows a
// redundant bare "sub" right next to every "resub". We hide the bare sub when a
// matching resub from the same user lands within this window. Kept short so a
// user's genuine original subscribe from a previous month (also a
// `channel.subscribe`) that happens to share the page is never mistaken for the
// duplicate of a current resub.
const RESUB_DEDUP_WINDOW_MS = 2 * 60 * 1000;

const displayRows = computed<DisplayRow[]>(() => {
  const list = props.events;
  const claimed = new Set<number>();
  const recipientsByGift = new Map<number, UnifiedEvent[]>();

  // Walk oldest -> newest so "the next N recipients" reads naturally in time.
  const chronological = [...list].reverse();
  for (let i = 0; i < chronological.length; i++) {
    const gift = chronological[i];
    if (gift.source !== 'twitch' || gift.event_type !== GIFT_EVENT) continue;

    const gd = gift.event_data ?? {};
    const total = Number(gd.total) || 0;
    if (total <= 0) continue;
    const tier = gd.tier;
    const broadcaster = gd.broadcaster_user_id;

    const recipients: UnifiedEvent[] = [];
    for (let j = i + 1; j < chronological.length && recipients.length < total; j++) {
      const sub = chronological[j];
      if (claimed.has(sub.id)) continue;
      if (sub.source !== 'twitch' || sub.event_type !== SUB_EVENT) continue;
      const sd = sub.event_data ?? {};
      if (sd.is_gift !== true) continue;
      if (broadcaster && sd.broadcaster_user_id !== broadcaster) continue;
      if (tier && sd.tier !== tier) continue;
      claimed.add(sub.id);
      recipients.push(sub);
    }

    if (recipients.length > 0) recipientsByGift.set(gift.id, recipients);
  }

  // Second pass: drop the bare self-sub that Twitch emits alongside each resub.
  // The gift pass above already claimed is_gift subs, so this only ever targets
  // genuine self-subscriptions.
  for (const resub of chronological) {
    if (resub.source !== 'twitch' || resub.event_type !== RESUB_EVENT) continue;
    const rd = resub.event_data ?? {};
    const userId = rd.user_id;
    if (!userId) continue;
    const broadcaster = rd.broadcaster_user_id;
    const resubTime = Date.parse(resub.created_at);

    // Claim the closest unclaimed self-sub from the same user within the window.
    let bestId = -1;
    let bestDelta = Infinity;
    for (const sub of chronological) {
      if (claimed.has(sub.id)) continue;
      if (sub.source !== 'twitch' || sub.event_type !== SUB_EVENT) continue;
      const sd = sub.event_data ?? {};
      if (sd.is_gift === true) continue;
      if (sd.user_id !== userId) continue;
      if (broadcaster && sd.broadcaster_user_id !== broadcaster) continue;
      const delta = Math.abs(Date.parse(sub.created_at) - resubTime);
      if (delta <= RESUB_DEDUP_WINDOW_MS && delta < bestDelta) {
        bestDelta = delta;
        bestId = sub.id;
      }
    }
    if (bestId >= 0) claimed.add(bestId);
  }

  const rows: DisplayRow[] = [];
  for (const event of list) {
    if (claimed.has(event.id)) continue;
    rows.push({ event, recipients: recipientsByGift.get(event.id) ?? [] });
  }
  return rows;
});

function isExpanded(id: number): boolean {
  return expandedGifts.value.has(id);
}

function toggleExpanded(id: number) {
  const next = new Set(expandedGifts.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  expandedGifts.value = next;
}

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
  'channel.follow': 'follow',
  'channel.subscribe': 'sub',
  'channel.subscription.message': 'resub',
  'channel.subscription.gift': 'gifted',
  'channel.cheer': 'cheer',
  'channel.raid': 'raid',
  'channel.channel_points_custom_reward_redemption.add': 'redeem',
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
  const progress = d.progress as number;
  const goal = d.goal as number;
  const level = d.level as number;
  if (event.event_type === 'channel.hype_train.begin') {
    return `Hype Train started level ${level}: ${progress} of ${goal}`;
  }
  if (event.event_type === 'channel.hype_train.progress') {
    return `Hype Train progressed to level ${level}: ${progress} of ${goal}`;
  }
  if (event.event_type === 'channel.hype_train.end') {
    return `Hype Train ended level ${level}.`;
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
      return d.tier ? `T${String(d.tier).replace('1000', '1').replace('2000', '2').replace('3000', '3')}` : null;
    case 'channel.subscription.gift':
      return d.total ? `${d.total} gifts` : null;
    case 'channel.cheer':
      return d.bits ? `${d.bits} bits` : null;
    case 'channel.raid':
      return d.viewers ? `${d.viewers} viewers` : null;
    case 'channel.channel_points_custom_reward_redemption.add':
      return ((d.reward as Record<string, unknown>)?.title as string) ?? null;
    case 'channel.channel_points_custom_reward_redemption.update':
      return null;
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
    <div v-for="{ event, recipients } in displayRows" :key="`${event.source}-${event.id}`" class="flex flex-col gap-1">
    <Popover
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
          <div class="flex flex-col md:flex-row min-w-0 flex-1 gap-0 group text-sm" :id="label(event)">
            <div class="flex flex-nowrap items-center gap-x-2 gap-y-1 max-w-full">
              <ProviderIcon :source="event.source"
                            class="h-4 w-4 shrink-0"
                            :class="eventDotClass(event)"
              />
              <div v-if="who(event)" class="font-bold max-w-40 overflow-hidden whitespace-nowrap text-ellipsis">{{ who(event) }}</div>
              <div class="group-hover:text-foreground whitespace-nowrap overflow-x-hidden md:max-w-90 text-ellipsis">{{ label(event) }}</div>
              <span v-if="details(event)" class="whitespace-nowrap max-w-40 overflow-hidden text-ellipsis">{{ details(event) }}</span>
              <button
                v-if="recipients.length"
                type="button"
                class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-sm border border-sidebar px-1.5 py-0.5 text-xs text-foreground hover:bg-background"
                :aria-expanded="isExpanded(event.id)"
                @click.stop="toggleExpanded(event.id)"
                @keydown.enter.stop
                @keydown.space.stop
              >
                <Gift class="h-3 w-3" />
                {{ recipients.length }}
                <ChevronDown v-if="isExpanded(event.id)" class="h-3 w-3" />
                <ChevronRight v-else class="h-3 w-3" />
              </button>
            </div>
            <div class="flex items-center gap-2 pl-4 text-xs w-full">
              <div class="whitespace-nowrap text-ellipsis ml-2 md:ml-auto">{{ relativeTime(event.created_at) }}</div>
              <RefreshCw v-if="replayingId === event.id" class="h-3 w-3 animate-spin" />
            </div>
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

    <!-- Gift-sub recipients, folded under the gifter's row -->
    <div v-if="recipients.length && isExpanded(event.id)" class="flex flex-col gap-1 border-l border-sidebar pl-4 ml-1">
      <div
        v-for="recipient in recipients"
        :key="`recipient-${recipient.id}`"
        class="flex items-center gap-2 text-xs text-foreground"
      >
        <div class="h-1.5 w-1.5 shrink-0 rounded-full bg-foreground/40"></div>
        <span class="font-medium">{{ who(recipient) }}</span>
        <span v-if="details(recipient)" class="text-foreground/70">{{ details(recipient) }}</span>
      </div>
    </div>
    </div>

  </div>
</template>
