<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import EventsTable from '@/components/EventsTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import { ChevronDown, ChevronLeft, ChevronRight, ChevronUp, RefreshCw, SlidersHorizontal, Volume2, VolumeX } from '@lucide/vue';
import debounce from 'lodash/debounce';
import { EVENT_TYPE_LABELS } from '@/composables/useEventColors';
import type { UnifiedEvent } from '@/composables/useEventColors';

const props = defineProps<{
  token: string;
}>();

interface PageMeta {
  current_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

interface FilterFacets {
  sources: string[];
  event_types: string[];
}

const events = ref<UnifiedEvent[]>([]);
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, total: 0, from: null, to: null });
const facets = ref<FilterFacets>({ sources: [], event_types: [] });
const filters = ref({ search: '', source: '', event_type: '', range: 'all' });
const page = ref(1);

const alertsMuted = ref(false);
const twitchId = ref('');

const refreshing = ref(false);
const muting = ref(false);
const filtersOpen = ref(false);
const showInfo = ref(false);
const initialized = ref(false);
const fatalError = ref<string | null>(null);
const loadError = ref<string | null>(null);
const muteError = ref<string | null>(null);
const replayNotice = ref<{ message: string; type: string } | null>(null);
let replayNoticeTimer: ReturnType<typeof setTimeout> | undefined;

function onReplayResult(result: { message: string; type: string }) {
  replayNotice.value = result;
  clearTimeout(replayNoticeTimer);
  replayNoticeTimer = setTimeout(() => {
    replayNotice.value = null;
  }, 6000);
}

async function load(showSpinner = true) {
  if (showSpinner) refreshing.value = true;
  try {
    const params = new URLSearchParams({ token: props.token });
    if (filters.value.search) params.set('search', filters.value.search);
    if (filters.value.source) params.set('source', filters.value.source);
    if (filters.value.event_type) params.set('event_type', filters.value.event_type);
    if (filters.value.range !== 'all') params.set('range', filters.value.range);
    if (page.value > 1) params.set('page', String(page.value));

    const res = await fetch(`/api/events?${params}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (res.status === 401) {
      fatalError.value = 'This feed link is invalid or expired. Copy a fresh events feed link from the Events page on Overlabels.';
      return;
    }
    if (res.status === 403) {
      fatalError.value = 'This token is not allowed to read the events feed.';
      return;
    }
    if (!res.ok) throw new Error(String(res.status));

    const json = await res.json();
    events.value = json.events.data;
    meta.value = {
      current_page: json.events.current_page,
      last_page: json.events.last_page,
      total: json.events.total,
      from: json.events.from,
      to: json.events.to,
    };
    facets.value = json.facets;
    alertsMuted.value = json.alerts_muted;
    loadError.value = null;
    initialized.value = true;

    if (!twitchId.value && json.twitch_id) {
      twitchId.value = String(json.twitch_id);
      subscribe();
    }
  } catch {
    loadError.value = 'Could not load events. Check your connection and try again.';
  } finally {
    setTimeout(() => {
      refreshing.value = false;
    }, 300);
  }
}

async function toggleMute() {
  if (muting.value) return;
  muting.value = true;
  muteError.value = null;
  try {
    const res = await fetch('/api/events/mute', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ token: props.token, muted: !alertsMuted.value }),
    });

    if (res.status === 403) {
      muteError.value = 'This token is not allowed to change alert settings.';
      return;
    }
    if (!res.ok) throw new Error(String(res.status));

    const json = await res.json();
    alertsMuted.value = json.alerts_muted;
  } catch {
    muteError.value = 'Could not update alert settings. Try again.';
  } finally {
    muting.value = false;
  }
}

function applyFilter() {
  page.value = 1;
  void load();
}

const debounceSearch = debounce(() => {
  applyFilter();
}, 300);

function goToPage(target: number) {
  if (target < 1 || target > meta.value.last_page || target === page.value) return;
  page.value = target;
  void load();
}

// Live updates: refetch page 1 (debounced) when new events land. Only the
// first page auto-refreshes; deeper pages would shift rows underneath you.
const refreshSoon = debounce(() => {
  if (page.value === 1) void load(false);
}, 1200);

function subscribe() {
  const echo = (window as any).Echo;
  if (!echo || !twitchId.value) return;

  echo.private(`twitch-events.${twitchId.value}`).listen('.twitch.event', () => refreshSoon());

  echo
    .private(`alerts.${twitchId.value}`)
    .listen('.control.updated', (e: { key?: string; value?: string }) => {
      // Mute state flips live no matter where it was toggled from.
      if (e.key === 'alerts:muted') {
        alertsMuted.value = e.value === '1';
      }
    })
    .listen('.alert.triggered', () => refreshSoon());
}

onMounted(() => {
  void load();
});

onBeforeUnmount(() => {
  clearTimeout(replayNoticeTimer);
  const echo = (window as any).Echo;
  if (echo && twitchId.value) {
    echo.leave(`twitch-events.${twitchId.value}`);
    echo.leave(`alerts.${twitchId.value}`);
  }
});

function sourceLabel(source: string): string {
  const map: Record<string, string> = {
    twitch: 'Twitch',
    kofi: 'Ko-fi',
    streamlabs: 'StreamLabs',
    streamelements: 'StreamElements',
    bmac: 'Buy Me a Coffee',
    fourthwall: 'Fourthwall',
  };
  return map[source] ?? source;
}

function eventTypeLabel(type: string): string {
  return EVENT_TYPE_LABELS[type] ?? type;
}
</script>

<template>
  <div v-if="fatalError" class="flex min-h-screen items-center justify-center px-6">
    <p class="max-w-md text-center text-sm text-foreground">{{ fatalError }}</p>
  </div>

  <div v-else class="mx-auto max-w-3xl px-2 py-2">
    <div class="mb-2 flex flex-wrap items-center gap-2">
      <button class="btn btn-chill btn-xs gap-1.5 cursor-pointer" :disabled="refreshing" @click="page = 1; load()">
        <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': refreshing }" />
        {{ refreshing ? 'Working' : 'Refresh' }}
      </button>

      <button
        class="btn btn-chill btn-xs gap-1.5 cursor-pointer"
        :aria-expanded="filtersOpen"
        aria-controls="feed-filters"
        @click="filtersOpen = !filtersOpen"
      >
        <SlidersHorizontal class="h-3 w-3" />
        Filters
        <ChevronUp v-if="filtersOpen" class="h-3 w-3" />
        <ChevronDown v-else class="h-3 w-3" />
      </button>

      <button
        class="btn btn-xs ml-auto gap-1.5 cursor-pointer"
        :class="alertsMuted ? 'border-amber-400/60 text-amber-400 hover:bg-amber-400/10' : 'btn-chill'"
        :disabled="muting || !initialized"
        :aria-pressed="alertsMuted"
        @click="toggleMute"
      >
        <VolumeX v-if="alertsMuted" class="h-3 w-3" />
        <Volume2 v-else class="h-3 w-3" />
        {{ alertsMuted ? 'Unmute alerts' : 'Mute all alerts' }}
      </button>

      <button
        class="grid h-7 w-7 cursor-pointer place-items-center rounded-full border border-violet-400/40 text-violet-400 transition hover:bg-violet-400/10"
        type="button"
        aria-label="Show info"
        @click="showInfo = true"
      >
        ?
      </button>
    </div>

    <div
      v-if="alertsMuted"
      class="mb-2 flex items-center gap-2 border border-amber-400/40 bg-amber-400/10 px-3 py-2 text-sm text-foreground"
    >
      <VolumeX class="h-4 w-4 shrink-0 text-amber-400" />
      <span>All alerts are muted. Events keep recording; unmute to fire alerts again.</span>
    </div>

    <div v-if="muteError" class="mb-2 border border-red-400/40 bg-red-400/10 px-3 py-2 text-sm text-foreground">
      {{ muteError }}
    </div>

    <div
      v-if="replayNotice"
      class="mb-2 border px-3 py-2 text-sm text-foreground"
      :class="{
        'border-violet-400/40 bg-violet-400/10': replayNotice.type === 'success',
        'border-amber-400/40 bg-amber-400/10': replayNotice.type === 'warning',
        'border-red-400/40 bg-red-400/10': replayNotice.type === 'error',
      }"
    >
      {{ replayNotice.message }}
    </div>

    <!-- Collapsible Filters -->
    <div v-show="filtersOpen" id="feed-filters" class="mb-2 border border-sidebar-border bg-sidebar-accent p-3">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <label for="feed-filter-search" class="text-xs">Search</label>
          <input
            v-model="filters.search"
            @input="debounceSearch"
            type="text"
            placeholder="Search event payload..."
            class="input-border h-9 w-full text-sm"
            id="feed-filter-search"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label for="feed-filter-source" class="text-xs">Source</label>
          <select
            v-model="filters.source"
            @change="applyFilter"
            class="input-border h-9 w-full cursor-pointer text-sm"
            id="feed-filter-source"
          >
            <option value="">All sources</option>
            <option v-for="src in facets.sources" :key="src" :value="src">
              {{ sourceLabel(src) }}
            </option>
          </select>
        </div>

        <div class="flex flex-col gap-1">
          <label for="feed-filter-event-type" class="text-xs">Event type</label>
          <select
            v-model="filters.event_type"
            @change="applyFilter"
            class="input-border h-9 w-full cursor-pointer text-sm"
            id="feed-filter-event-type"
          >
            <option value="">All event types</option>
            <option v-for="type in facets.event_types" :key="type" :value="type">
              {{ eventTypeLabel(type) }}
            </option>
          </select>
        </div>

        <div class="flex flex-col gap-1">
          <label for="feed-filter-range" class="text-xs">Time range</label>
          <select
            v-model="filters.range"
            @change="applyFilter"
            class="input-border h-9 w-full cursor-pointer text-sm"
            id="feed-filter-range"
          >
            <option value="all">All time</option>
            <option value="hour">Last hour</option>
            <option value="24h">Last 24 hours</option>
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="loadError" class="border border-red-400/40 bg-red-400/10 px-3 py-2 text-sm text-foreground">
      {{ loadError }}
    </div>

    <div v-else-if="initialized" class="bg-card px-2 py-1 transition-opacity duration-300" :class="refreshing ? 'opacity-40' : 'opacity-100'">
      <EventsTable v-if="events.length > 0" :events="events" :token="token" @replay-result="onReplayResult" />

      <EmptyState v-else message="No events match your filters. Try widening the time range or clearing search." />

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between gap-2 pb-2 text-sm">
        <button
          class="btn btn-chill btn-xs gap-1 cursor-pointer"
          :disabled="meta.current_page <= 1 || refreshing"
          @click="goToPage(meta.current_page - 1)"
        >
          <ChevronLeft class="h-3 w-3" />
          Newer
        </button>
        <span class="tabular-nums text-foreground">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button
          class="btn btn-chill btn-xs gap-1 cursor-pointer"
          :disabled="meta.current_page >= meta.last_page || refreshing"
          @click="goToPage(meta.current_page + 1)"
        >
          Older
          <ChevronRight class="h-3 w-3" />
        </button>
      </div>
    </div>
  </div>

  <div
    v-if="showInfo"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
    @click.self="showInfo = false"
  >
    <div class="w-full max-w-md rounded-xl bg-background p-5 shadow-xl">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium leading-6">
          Your recent events, live. Use the mute button to silence every alert in one tap - visuals, sounds, TTS and bot messages. Events keep recording while muted. Tap an event to replay its alert on stream; unmute first, replay is blocked while alerts are muted.
        </p>

        <button
          class="cursor-pointer text-lg leading-none text-base-content/60 hover:text-base-content"
          type="button"
          @click="showInfo = false"
        >
          ×
        </button>
      </div>
    </div>
  </div>
</template>
