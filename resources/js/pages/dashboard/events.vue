<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import EventsTable from '@/components/EventsTable.vue';
import Pagination from '@/components/Pagination.vue';
import RekaToast from '@/components/RekaToast.vue';
import EmptyState from '@/components/EmptyState.vue';
import { ChevronDown, ChevronUp, RefreshCw, SlidersHorizontal } from 'lucide-vue-next';
import debounce from 'lodash/debounce';
import { EVENT_TYPE_LABELS } from '@/composables/useEventColors';
import type { AppPageProps } from '@/types';

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
  events: PaginatedEvents;
  filters?: FiltersShape;
  facets: FilterFacets;
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
  router.get(route('dashboard.events'), buildQuery(), {
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
const showInfo = ref(false);
const filtersOpen = ref(false);

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
    only: ['events', 'facets'],
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
</script>

<template>
  <Head>
    <title>Stream Events</title>
    <meta name="description" content="Recent stream events - Overlabels" />
  </Head>

  <div class="mx-auto max-w-3xl px-2 py-2">
    <div class="mb-2 flex flex-wrap items-center gap-2">
      <button class="btn btn-chill btn-xs gap-1.5 cursor-pointer" :disabled="refreshing" @click="refresh">
        <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': refreshing }" />
        {{ refreshing ? 'Working' : 'Refresh' }}
      </button>

      <button
        class="btn btn-chill btn-xs gap-1.5 cursor-pointer"
        :aria-expanded="filtersOpen"
        aria-controls="event-filters"
        @click="filtersOpen = !filtersOpen"
      >
        <SlidersHorizontal class="h-3 w-3" />
        Filters
        <ChevronUp v-if="filtersOpen" class="h-3 w-3" />
        <ChevronDown v-else class="h-3 w-3" />
      </button>

      <button
        class="ml-auto grid h-7 w-7 cursor-pointer place-items-center rounded-full border border-violet-400/40 text-violet-400 transition hover:bg-violet-400/10"
        type="button"
        aria-label="Show info"
        @click="showInfo = true"
      >
        ?
      </button>
    </div>

    <!-- Collapsible Filters -->
    <div
      v-show="filtersOpen"
      id="event-filters"
      class="mb-2 border border-sidebar-border bg-sidebar-accent p-3"
    >
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <label for="embed-filter-search" class="text-xs">Search</label>
          <input
            v-model="filters.search"
            @input="debounceSearch"
            type="text"
            placeholder="Search event payload..."
            class="input-border h-9 w-full text-sm"
            id="embed-filter-search"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label for="embed-filter-source" class="text-xs">Source</label>
          <select
            v-model="filters.source"
            @change="applyFilter"
            class="input-border h-9 w-full cursor-pointer text-sm"
            id="embed-filter-source"
          >
            <option value="">All sources</option>
            <option v-for="src in facets.sources" :key="src" :value="src">
              {{ sourceLabel(src) }}
            </option>
          </select>
        </div>

        <div class="flex flex-col gap-1">
          <label for="embed-filter-event-type" class="text-xs">Event type</label>
          <select
            v-model="filters.event_type"
            @change="applyFilter"
            class="input-border h-9 w-full cursor-pointer text-sm"
            id="embed-filter-event-type"
          >
            <option value="">All event types</option>
            <option v-for="type in facets.event_types" :key="type" :value="type">
              {{ eventTypeLabel(type) }}
            </option>
          </select>
        </div>

        <div class="flex flex-col gap-1">
          <label for="embed-filter-range" class="text-xs">Time range</label>
          <select
            v-model="filters.range"
            @change="applyFilter"
            class="input-border h-9 w-full cursor-pointer text-sm"
            id="embed-filter-range"
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

    <div class="transition-opacity duration-300 bg-card px-2 py-1" :class="refreshing ? 'opacity-40' : 'opacity-100'">
      <EventsTable v-if="events.data.length > 0" :events="events.data" />

      <EmptyState v-else
                  message="No events match your filters. Try widening the time range or clearing search." />

      <div v-if="events.last_page > 1" class="mt-4">
        <Pagination
          :links="events.links"
          :from="events.from"
          :to="events.to"
          :total="events.total"
        />
      </div>
    </div>
  </div>

  <div
    v-if="showInfo"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
    @click.self="showInfo = false"
  >
    <div class="w-full max-w-md rounded-xl bg-base-100 p-5 shadow-xl bg-background">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium leading-6">
          Your recent events. click an event and tap Yes to replay the event in your overlay(s)
        </p>

        <button class="text-lg leading-none text-base-content/60 hover:text-base-content cursor-pointer" type="button"
                @click="showInfo = false">
          ×
        </button>
      </div>
    </div>
  </div>

  <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />
</template>
