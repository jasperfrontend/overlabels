<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import EventsTable from '@/components/EventsTable.vue';
import Pagination from '@/components/Pagination.vue';
import RekaToast from '@/components/RekaToast.vue';
import EmptyState from '@/components/EmptyState.vue';
import { ExternalLink, ListPlus, Radio, RefreshCw } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import debounce from 'lodash/debounce';
import { EVENT_TYPE_LABELS } from '@/composables/useEventColors';
import type { AppPageProps, OverlayTemplate } from '@/types';

interface FeedList {
  id: number;
  slug: string;
  label: string | null;
  max_items: number | null;
  feed_enabled: boolean;
  feed_types: string[];
}

interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  label?: string | null;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

interface PaginationLink {
  url: string | null;
  label: string;
  page: number | null;
  active: boolean;
}

interface PaginatedEvents {
  data: UnifiedEvent[];
  links: PaginationLink[];
  from: number;
  to: number;
  total: number;
  last_page: number;
  current_page: number;
}

interface FiltersShape {
  search?: string;
  source?: string;
  event_type?: string;
  range?: string;
}

interface FilterFacets {
  sources: string[];
  event_types: string[];
}

const props = defineProps<{
  recentTemplates: OverlayTemplate[];
  recentEvents: PaginatedEvents;
  filters?: FiltersShape;
  facets: FilterFacets;
  userLists: FeedList[];
}>();

function normalizeFilters(input?: FiltersShape) {
  return {
    search: input?.search || '',
    source: input?.source || '',
    event_type: input?.event_type || '',
    range: input?.range || 'all',
  };
}

const filters = ref(normalizeFilters(props.filters));

watch(
  () => props.filters,
  (newFilters) => {
    filters.value = normalizeFilters(newFilters);
  },
  { deep: true },
);

function buildQuery(): Record<string, string> {
  const params: Record<string, string> = {};
  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.source) params.source = filters.value.source;
  if (filters.value.event_type) params.event_type = filters.value.event_type;
  if (filters.value.range && filters.value.range !== 'all') params.range = filters.value.range;
  return params;
}

function applyFilter() {
  router.get(route('dashboard.recents'), buildQuery(), {
    preserveState: true,
    preserveScroll: true,
  });
}

const debounceSearch = debounce(() => {
  applyFilter();
}, 300);

const page = usePage<AppPageProps>();
const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');

watch(
  () => page.props.flash?.message,
  (newMessage) => {
    if (newMessage) {
      toastMessage.value = newMessage;
      toastType.value = (page.props.flash?.type as typeof toastType.value) || 'info';
    }
  },
  { immediate: true }
);

const refreshing = ref(false);

function refresh() {
  if (refreshing.value) return;
  refreshing.value = true;
  router.reload({
    only: ['recentEvents', 'recentTemplates', 'facets', 'userLists'],
    onFinish: () => {
      setTimeout(() => {
        refreshing.value = false;
      }, 600);
    }
  });
}

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

/* -------- Recent-events feed: point a list at this event stream -------- */

const selectedListId = ref<number | null>(null);
const feedEnabled = ref(false);
const allTypes = ref(true);
const selectedTypes = ref<string[]>([]);
const feedMaxItems = ref<number>(50);
const savingFeed = ref(false);
const feedSaved = ref(false);

const selectedList = computed(() =>
  props.userLists.find((l) => l.id === selectedListId.value) ?? null,
);

// Prefill the controls from the chosen list's existing feed config so saving
// without changes is a no-op rather than a surprise reset.
watch(selectedListId, () => {
  const list = selectedList.value;
  if (!list) return;
  feedEnabled.value = list.feed_enabled;
  allTypes.value = list.feed_types.length === 0;
  selectedTypes.value = [...list.feed_types];
  feedMaxItems.value = list.max_items ?? 50;
  feedSaved.value = false;
});

function toggleType(type: string, checked: boolean) {
  if (checked) {
    if (!selectedTypes.value.includes(type)) selectedTypes.value.push(type);
  } else {
    selectedTypes.value = selectedTypes.value.filter((t) => t !== type);
  }
}

function saveFeed() {
  if (!selectedListId.value || savingFeed.value) return;
  savingFeed.value = true;
  feedSaved.value = false;
  router.put(
    route('lists.event-feed', selectedListId.value),
    {
      enabled: feedEnabled.value,
      types: allTypes.value ? [] : selectedTypes.value,
      max_items: feedMaxItems.value,
    },
    {
      preserveScroll: true,
      preserveState: false,
      onSuccess: () => {
        feedSaved.value = true;
      },
      onFinish: () => {
        savingFeed.value = false;
      },
    },
  );
}

const breadcrumbs = [
  {
    title: 'Dashboard',
    href: '/dashboard'
  },
  {
    title: 'Recent events',
    href: '/dashboard/recents'
  }
];
</script>

<template>
  <Head>
    <title>My activity</title>
    <meta name="description" content="Your recent templates and stream events - Overlabels" />
  </Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-8 p-4">
      <!-- Recent Stream Events -->
      <section class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <Radio class="mr-1 h-6 w-6" />
            <Heading title="Recent alerts and stream events" />
            <button class="btn btn-chill btn-xs gap-1.5 cursor-pointer" :disabled="refreshing" @click="refresh">
              <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': refreshing }" />
              {{ refreshing ? 'Working' : 'Refresh' }}
            </button>
          </div>
          <a href="/dashboard/events" target="_blank" class="btn btn-primary self-start sm:self-auto cursor-pointer">
            Embed view
            <ExternalLink class="ml-2 h-4 w-4" />
          </a>
        </div>

        <!-- Filters Section -->
        <div class="mb-4 border border-sidebar-border bg-sidebar-accent p-4">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Search -->
            <div class="flex flex-col gap-1">
              <label for="filter-search">Search</label>
              <input
                v-model="filters.search"
                @input="debounceSearch"
                type="text"
                placeholder="Search event payload..."
                class="input-border h-10 w-full"
                id="filter-search"
              />
            </div>

            <!-- Source -->
            <div class="flex flex-col gap-1">
              <label for="filter-source">Source</label>
              <select
                v-model="filters.source"
                @change="applyFilter"
                class="input-border h-10 w-full cursor-pointer"
                id="filter-source"
              >
                <option value="">All sources</option>
                <option v-for="src in facets.sources" :key="src" :value="src">
                  {{ sourceLabel(src) }}
                </option>
              </select>
            </div>

            <!-- Event Type -->
            <div class="flex flex-col gap-1">
              <label for="filter-event-type">Event type</label>
              <select
                v-model="filters.event_type"
                @change="applyFilter"
                class="input-border h-10 w-full cursor-pointer"
                id="filter-event-type"
              >
                <option value="">All event types</option>
                <option v-for="type in facets.event_types" :key="type" :value="type">
                  {{ eventTypeLabel(type) }}
                </option>
              </select>
            </div>

            <!-- Time Range -->
            <div class="flex flex-col gap-1">
              <label for="filter-range">Time range</label>
              <select
                v-model="filters.range"
                @change="applyFilter"
                class="input-border h-10 w-full cursor-pointer"
                id="filter-range"
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

        <!-- Send these events to a list -->
        <div class="mb-4 border border-sidebar-border bg-sidebar-accent p-4">
          <div class="flex items-start gap-3">
            <ListPlus class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="min-w-0 flex-1 space-y-1">
              <h3 class="font-semibold text-foreground">Send these events to a list</h3>
              <p class="text-sm text-foreground">
                Mirror your recent events into one of your Lists - a live "recent events" feed you can drop into any overlay
                (loop it with <code class="rounded-sm bg-background px-1 py-0.5 text-xs">foreach</code> and cap with
                <code class="rounded-sm bg-background px-1 py-0.5 text-xs">list.x.index</code>) or read from your own app over websockets.
                Turning it on backfills the list with events that already happened.
              </p>
            </div>
          </div>

          <div v-if="userLists.length === 0" class="mt-3 text-sm text-foreground">
            You don't have any editable lists yet.
            <a href="/dashboard/lists" class="text-primary underline cursor-pointer">Create a list</a> first, then come back here.
          </div>

          <div v-else class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <!-- Target list -->
              <div class="flex flex-col gap-1">
                <label for="feed-list">Target list</label>
                <select
                  v-model="selectedListId"
                  class="input-border h-10 w-full cursor-pointer"
                  id="feed-list"
                >
                  <option :value="null">- pick a list -</option>
                  <option v-for="l in userLists" :key="l.id" :value="l.id">
                    {{ l.label || l.slug }}{{ l.feed_enabled ? ' (feed on)' : '' }}
                  </option>
                </select>
              </div>

              <!-- Keep latest N -->
              <div class="flex flex-col gap-1">
                <label for="feed-cap">Keep latest</label>
                <input
                  v-model.number="feedMaxItems"
                  :disabled="!selectedList"
                  type="number"
                  min="1"
                  max="500"
                  class="input-border h-10 w-full disabled:opacity-50"
                  id="feed-cap"
                />
              </div>

              <!-- Enabled -->
              <div class="flex flex-col gap-1">
                <label>Feed</label>
                <label class="flex h-10 items-center gap-2" :class="selectedList ? 'cursor-pointer' : 'opacity-50'">
                  <input type="checkbox" v-model="feedEnabled" :disabled="!selectedList" class="cursor-pointer" />
                  <span class="text-sm text-foreground">{{ feedEnabled ? 'Enabled' : 'Disabled' }}</span>
                </label>
              </div>
            </div>

            <!-- Event type filter -->
            <fieldset v-if="selectedList" class="space-y-2" :disabled="!feedEnabled" :class="feedEnabled ? '' : 'opacity-50'">
              <label class="flex w-fit items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="allTypes" class="cursor-pointer" />
                <span class="text-sm text-foreground">All event types</span>
              </label>

              <div v-if="!allTypes" class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                <label
                  v-for="type in facets.event_types"
                  :key="type"
                  class="flex items-center gap-2 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :checked="selectedTypes.includes(type)"
                    @change="toggleType(type, ($event.target as HTMLInputElement).checked)"
                    class="cursor-pointer"
                  />
                  <span class="truncate text-sm text-foreground">{{ eventTypeLabel(type) }}</span>
                </label>
              </div>
              <p v-if="!allTypes && facets.event_types.length === 0" class="text-sm text-foreground">
                No event types recorded yet - leave "All event types" on to capture everything going forward.
              </p>
            </fieldset>

            <div class="flex items-center gap-3">
              <button
                class="btn btn-primary cursor-pointer disabled:opacity-50"
                :disabled="!selectedList || savingFeed"
                @click="saveFeed"
              >
                {{ savingFeed ? 'Saving' : 'Save feed' }}
              </button>
              <a
                v-if="selectedList"
                :href="`/dashboard/lists/${selectedList.slug}`"
                class="text-sm text-primary underline cursor-pointer"
              >
                View list
              </a>
              <span v-if="feedSaved" class="text-sm text-green-500">Saved</span>
            </div>
          </div>
        </div>

        <div class="transition-opacity duration-300" :class="refreshing ? 'opacity-40' : 'opacity-100'">
          <EventsTable v-if="recentEvents.data.length > 0" :events="recentEvents.data" />

          <EmptyState
            v-else
            message="No events match your filters. Try widening the time range or clearing search."
          />

          <!-- Pagination -->
          <div v-if="recentEvents.last_page > 1" class="mt-6">
            <Pagination
              :links="recentEvents.links"
              :from="recentEvents.from"
              :to="recentEvents.to"
              :total="recentEvents.total"
            />
          </div>
        </div>
      </section>
    </div>

    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
  </AppLayout>
</template>
