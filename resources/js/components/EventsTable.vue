<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RefreshCw, ChevronDown, ChevronRight, Gift } from '@lucide/vue';
import { useEventColors, eventLabel } from '@/composables/useEventColors';
import type { UnifiedEvent } from '@/composables/useEventColors';
import ProviderIcon from '@/components/ProviderIcon.vue';
import { outcomeLabel } from '@/utils/deliveryOutcome';

const { eventHoverBorderClass, eventDotClass } = useEventColors();

const props = defineProps<{
  events: UnifiedEvent[];
  // When set, replay posts to the token-authed /api endpoints instead of the
  // session-authed dashboard routes; used by the events feed, which has no
  // session and no Inertia. Requires the token's `write` ability server-side.
  token?: string;
  // Opt-in row selection for bulk delete. Only the session-authed recents page
  // passes this - the token-authed feed shares this component but must not be
  // able to destroy events.
  selectable?: boolean;
  // Selected row keys, `${source}:${id}`. Ids collide between twitch_events and
  // external_events, so the source has to be part of the key.
  selection?: string[];
}>();

const emit = defineEmits<{
  // Replay outcome for pages without Inertia flash messages (the events feed).
  'replay-result': [result: { message: string; type: string }];
  'update:selection': [keys: string[]];
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
  // Rows this one absorbed and that are therefore not rendered on their own:
  // gift recipients, plus the bare sub that the resub pass hides. Selection has
  // to carry them, otherwise deleting the visible row un-hides its leftovers
  // and they reappear in the feed ungrouped.
  covered: UnifiedEvent[];
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
  // Every hidden row, keyed by the visible row that swallowed it. All the
  // grouping below is Twitch-only, so plain numeric ids are unambiguous here.
  const coveredByOwner = new Map<number, UnifiedEvent[]>();

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

    if (recipients.length > 0) {
      recipientsByGift.set(gift.id, recipients);
      coveredByOwner.set(gift.id, [...recipients]);
    }
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
    let best: UnifiedEvent | null = null;
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
        best = sub;
      }
    }
    if (best) {
      claimed.add(best.id);
      coveredByOwner.set(resub.id, [...(coveredByOwner.get(resub.id) ?? []), best]);
    }
  }

  const rows: DisplayRow[] = [];
  for (const event of list) {
    if (claimed.has(event.id)) continue;
    rows.push({
      event,
      recipients: recipientsByGift.get(event.id) ?? [],
      covered: coveredByOwner.get(event.id) ?? [],
    });
  }
  return rows;
});

/* ----------------------------- Row selection ----------------------------- */

function rowKey(event: UnifiedEvent): string {
  return `${event.source}:${event.id}`;
}

// A row stands for itself plus everything it hid, so selecting it deletes the
// whole visual group rather than stranding its recipients.
function keysFor(event: UnifiedEvent, covered: UnifiedEvent[]): string[] {
  return [rowKey(event), ...covered.map(rowKey)];
}

const selectedKeys = computed(() => new Set(props.selection ?? []));

function isRowSelected(event: UnifiedEvent): boolean {
  return selectedKeys.value.has(rowKey(event));
}

function toggleRow(event: UnifiedEvent, covered: UnifiedEvent[]) {
  const next = new Set(selectedKeys.value);
  const keys = keysFor(event, covered);
  if (isRowSelected(event)) keys.forEach((k) => next.delete(k));
  else keys.forEach((k) => next.add(k));
  emit('update:selection', [...next]);
}

const pageKeys = computed(() => displayRows.value.flatMap((r) => keysFor(r.event, r.covered)));

const allOnPageSelected = computed(() => displayRows.value.length > 0 && displayRows.value.every((r) => isRowSelected(r.event)));

function togglePage() {
  const next = new Set(selectedKeys.value);
  if (allOnPageSelected.value) pageKeys.value.forEach((k) => next.delete(k));
  else pageKeys.value.forEach((k) => next.add(k));
  emit('update:selection', [...next]);
}

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

  const url = event.source === 'twitch' ? `/events/${event.id}/replay` : `/external-events/${event.id}/replay`;
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
  const url = event.source === 'twitch' ? `/api/events/${event.id}/replay` : `/api/external-events/${event.id}/replay`;
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

/* ------------------------------- Row copy --------------------------------
   Each row reads KIND then who then the rest: a monospace uppercase kind tag
   carries the event type, the acting user is bold, and details() carries the
   payload ("500 bits", a reward title). `phrase()` holds whatever part of the
   old one-string label was not the kind ("went live", poll/goal verbs).
*/

interface RowLabel {
  kind: string;
  phrase?: string;
}

const twitchRowLabels: Record<string, RowLabel> = {
  'channel.follow': { kind: 'follow' },
  'channel.subscribe': { kind: 'sub' },
  'channel.subscription.message': { kind: 'resub' },
  'channel.subscription.gift': { kind: 'gift sub' },
  'channel.cheer': { kind: 'cheer' },
  'channel.raid': { kind: 'raid' },
  'channel.channel_points_custom_reward_redemption.add': { kind: 'redeem' },
  'channel.channel_points_custom_reward_redemption.update': { kind: 'redeem', phrase: 'redemption updated' },
  'stream.online': { kind: 'stream', phrase: 'went live' },
  'stream.offline': { kind: 'stream', phrase: 'ended the stream' },
  'channel.poll.begin': { kind: 'poll', phrase: 'started' },
  'channel.poll.progress': { kind: 'poll', phrase: 'updated' },
  'channel.poll.end': { kind: 'poll', phrase: 'ended' },
  'channel.goal.begin': { kind: 'goal', phrase: 'started' },
  'channel.goal.progress': { kind: 'goal', phrase: 'progressed' },
  'channel.goal.end': { kind: 'goal', phrase: 'ended' },
};

const externalEventLabels: Record<string, Record<string, string>> = {
  checkin: {
    checkin: 'checkin',
  },
  kofi: {
    donation: 'Ko-fi tip',
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
};

const HYPE_TRAIN_PREFIX = 'channel.hype_train.';

function kind(event: UnifiedEvent): string {
  if (event.source === 'twitch') {
    if (event.event_type.startsWith(HYPE_TRAIN_PREFIX)) return 'hype train';
    return twitchRowLabels[event.event_type]?.kind ?? event.label ?? event.event_type;
  }
  return externalEventLabels[event.source]?.[event.event_type] ?? eventLabel({ eventType: event.event_type, service: event.source });
}

function phrase(event: UnifiedEvent): string | null {
  if (event.source !== 'twitch') return null;
  if (event.event_type.startsWith(HYPE_TRAIN_PREFIX)) return hypeTrainPhrase(event);
  return twitchRowLabels[event.event_type]?.phrase ?? null;
}

function hypeTrainPhrase(event: UnifiedEvent): string {
  const d = event.event_data as Record<string, unknown>;
  const progress = d.progress as number;
  const goal = d.goal as number;
  const level = d.level as number;
  if (event.event_type === 'channel.hype_train.begin') return `started level ${level}: ${progress} of ${goal}`;
  if (event.event_type === 'channel.hype_train.progress') return `level ${level}: ${progress} of ${goal}`;
  if (event.event_type === 'channel.hype_train.end') return `ended at level ${level}`;
  return event.event_type;
}

function who(event: UnifiedEvent): string | null {
  if (event.source !== 'twitch') {
    // Most drivers write `event.from_name`; checkin names its actor
    // `event.user_name`. Drivers cast absent values to '', hence || not ??.
    const p = event.normalized_payload;
    return (p?.['event.from_name'] as string) || (p?.['event.user_name'] as string) || null;
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
    if (tier) return tier;
    // Checkin events carry a place instead of an amount.
    return (p['event.place'] as string) || null;
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

// Compact single-unit age ("2m", "4h", "3d") - the full timestamp lives in the
// title attribute. Dense rows earn their density here; "2 minutes ago" was the
// widest thing on every row.
function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const minute = 60_000;
  const hour = 60 * minute;
  const day = 24 * hour;
  const week = 7 * day;
  if (diff < minute) return 'now';
  if (diff < hour) return `${Math.floor(diff / minute)}m`;
  if (diff < day) return `${Math.floor(diff / hour)}h`;
  if (diff < week) return `${Math.floor(diff / day)}d`;
  return `${Math.floor(diff / week)}w`;
}

function fullTime(iso: string): string {
  return new Date(iso).toLocaleString();
}
</script>

<template>
  <div class="mt-4">
    <label v-if="selectable && displayRows.length > 0" class="mb-2 flex w-fit cursor-pointer items-center gap-2 text-xs text-foreground">
      <input type="checkbox" class="cursor-pointer" :checked="allOnPageSelected" @change="togglePage" />
      Select everything on this page
    </label>

    <div class="flex flex-col divide-y divide-foreground/5">
      <div v-for="{ event, recipients, covered } in displayRows" :key="`${event.source}-${event.id}`" class="flex items-start gap-2">
        <input
          v-if="selectable"
          type="checkbox"
          class="mt-3 shrink-0 cursor-pointer"
          :checked="isRowSelected(event)"
          :aria-label="`Select ${who(event) ? who(event) + ' ' : ''}${kind(event)}`"
          @change="toggleRow(event, covered)"
        />
        <div class="min-w-0 flex-1">
          <Popover :open="confirmingId === event.id" @update:open="(open: boolean) => (confirmingId = open && canReplay(event) ? event.id : null)">
            <PopoverTrigger as-child>
              <div
                :class="[
                  'group collection-row flex flex-col gap-1 px-3 py-2 text-sm sm:flex-row sm:items-center sm:gap-3',
                  eventHoverBorderClass(event),
                  canReplay(event) && confirmingId !== event.id ? 'cursor-pointer' : '',
                  confirmingId !== null && confirmingId !== event.id ? 'opacity-30' : '',
                  confirmingId === event.id ? 'border-violet-400 dark:border-violet-300' : '',
                ]"
                :role="canReplay(event) ? 'button' : undefined"
                :tabindex="canReplay(event) ? 0 : undefined"
                @click="canReplay(event) && confirmingId !== event.id ? openConfirm(event) : undefined"
                @keydown.enter.prevent="openConfirm(event)"
                @keydown.space.prevent="openConfirm(event)"
              >
                <div class="flex min-w-0 flex-1 items-center gap-2.5">
                  <!-- Shape carries the source, color reinforces the event type.
                       Both stay on at rest - the pairing is deliberate, never
                       reduce it to hover-only. -->
                  <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-foreground/[0.06] transition-colors group-hover:bg-foreground/10"
                    aria-hidden="true"
                  >
                    <ProviderIcon :source="event.source" class="h-3.5 w-3.5" :class="eventDotClass(event)" />
                  </span>
                  <span
                    class="shrink-0 font-mono text-[10px] font-medium tracking-wider text-foreground/50 uppercase transition-colors group-hover:text-foreground/75"
                  >
                    {{ kind(event) }}
                  </span>
                  <span v-if="who(event)" class="min-w-0 truncate font-semibold text-foreground">{{ who(event) }}</span>
                  <span v-if="phrase(event)" class="min-w-0 truncate text-foreground/80">{{ phrase(event) }}</span>
                  <span v-if="details(event)" class="min-w-0 truncate text-foreground/70">{{ details(event) }}</span>
                  <button
                    v-if="recipients.length"
                    type="button"
                    class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-full border border-foreground/15 px-2 py-0.5 text-xs text-foreground/70 hover:bg-foreground/5 hover:text-foreground"
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

                <div class="flex shrink-0 items-center gap-2 pl-9 sm:pl-0">
                  <!-- What became of this event's alert. Nothing for rows from before the ledger. -->
                  <span v-if="outcomeLabel(event.outcome)" class="text-[11px] whitespace-nowrap text-foreground/50">
                    {{ outcomeLabel(event.outcome) }}
                  </span>
                  <span class="font-mono text-[10px] whitespace-nowrap text-foreground/40 tabular-nums" :title="fullTime(event.created_at)">
                    {{ relativeTime(event.created_at) }}
                  </span>
                  <RefreshCw v-if="replayingId === event.id" class="h-3 w-3 animate-spin" />
                  <!-- Hover-revealed from md up; always visible below, where there
                       is no hover to reveal it. The whole row triggers the same
                       confirm - this is the affordance that says so. On
                       non-replayable rows it stays as an invisible spacer so the
                       time column lines up down the whole list. -->
                  <button
                    type="button"
                    class="shrink-0 cursor-pointer rounded-full border border-foreground/15 px-3 py-0.5 text-[11px] font-medium text-foreground/70 transition-opacity hover:bg-foreground/5 hover:text-foreground md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                    :class="[canReplay(event) ? '' : 'invisible', confirmingId === event.id ? 'md:opacity-100' : '']"
                    @click.stop="openConfirm(event)"
                    @keydown.enter.stop
                    @keydown.space.stop
                  >
                    Replay
                  </button>
                </div>
              </div>
            </PopoverTrigger>

            <PopoverContent class="w-auto bg-accent p-3" side="top" :side-offset="-1" align="start">
              <div class="flex items-center gap-3">
                <!-- The dashboard and unified feed send external events without
                     a server label - fall back to the row's own kind tag. -->
                <span class="text-sm text-foreground">Replay &ldquo;{{ event.label || kind(event) }}&rdquo;?</span>
                <button :ref="(el: any) => el?.focus({ focusVisible: true })" class="btn btn-primary btn-xs" @click="confirmAndReplay(event)">
                  Yes
                </button>
                <button class="btn btn-chill btn-xs" @click="confirmingId = null">Cancel</button>
              </div>
            </PopoverContent>
          </Popover>

          <!-- Gift-sub recipients, folded under the gifter's row -->
          <div v-if="recipients.length && isExpanded(event.id)" class="mb-2 ml-6 flex flex-col gap-1 border-l border-foreground/10 pl-4">
            <div v-for="recipient in recipients" :key="`recipient-${recipient.id}`" class="flex items-center gap-2 text-xs text-foreground">
              <div class="h-1.5 w-1.5 shrink-0 rounded-full bg-foreground/40"></div>
              <span class="font-medium">{{ who(recipient) }}</span>
              <span v-if="details(recipient)" class="text-foreground/70">{{ details(recipient) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
