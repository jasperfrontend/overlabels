<script setup lang="ts">
import { computed, watchEffect } from 'vue';
import { router, Link, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import TemplateCollection from '@/components/TemplateCollection.vue';
import FilterBar from '@/components/FilterBar.vue';
import FilterSearchInput from '@/components/FilterSearchInput.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import { useSearchFilters } from '@/composables/useSearchFilters';
import { PlusIcon, Layers, Bell, Blocks } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import type { BreadcrumbItem } from '@/types/index.js';
import type { AppPageProps } from '@/types';
import { recordListContext } from '@/composables/useListContext';

interface FiltersShape {
  filter?: string;
  search?: string;
  type?: string;
  sort?: string;
  direction?: string;
}

const props = defineProps<{
  templates?: Record<string, any>;
  filters?: FiltersShape;
}>();

function normalizeFilters(input?: FiltersShape) {
  // Only accept string values. An empty PHP `$request->only()` serializes to a
  // JSON array, which arrives here as `[]` - reading `.sort`/`.filter` off that
  // yields native Array methods (functions), not strings, which then leak into
  // the query string and crash the ORDER BY. Guarding on typeof keeps us safe.
  const str = (val: unknown) => (typeof val === 'string' ? val : '');
  return {
    filter: str(input?.filter) || 'all_templates',
    search: str(input?.search),
    type: str(input?.type),
    sort: str(input?.sort) || 'created_at',
    direction: str(input?.direction) || 'desc',
  };
}

// Build a query object that strips empty strings and default values, so the
// URL stays canonical and doesn't bounce between equivalent forms (e.g.
// `?search=` vs `?search` vs no search param at all). Bouncing creates
// spurious browser history entries that break back/forward navigation.
function buildQuery(): Record<string, string> {
  const params: Record<string, string> = {};
  if (filters.value.filter && filters.value.filter !== 'all_templates') {
    params.filter = filters.value.filter;
  }
  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.type) params.type = filters.value.type;
  if (filters.value.sort && filters.value.sort !== 'created_at') {
    params.sort = filters.value.sort;
  }
  if (filters.value.direction && filters.value.direction !== 'desc') {
    params.direction = filters.value.direction;
  }
  return params;
}

const { filters, applyFilter, debounceSearch } = useSearchFilters({
  serverFilters: () => props.filters,
  normalize: normalizeFilters,
  apply: () =>
    router.get(route('templates.index'), buildQuery(), {
      preserveState: true,
      preserveScroll: true,
      // Search-as-you-type would otherwise leave a history entry per keystroke
      // batch, so going back walks you through `t`, `te`, `tes` before leaving.
      replace: true,
    }),
});

const typeOptions = [
  { value: '', label: 'All Types' },
  { value: 'static', label: 'Static overlay' },
  { value: 'alert', label: 'Event alert' },
  { value: 'block', label: 'Block' },
];

const ownerOptions = [
  { value: 'all_templates', label: 'All overlays' },
  { value: 'mine', label: 'My overlays' },
  { value: 'public', label: 'Public overlays' },
];

const sortOptions = [
  { value: 'created_at', label: 'Date created' },
  { value: 'name', label: 'Name' },
  { value: 'view_count', label: 'Views' },
  { value: 'fork_count', label: 'Copies' },
];

const page = usePage<AppPageProps>();
const currentUserId = computed(() => page.props.auth.user.id);

const pageTitle = computed(() => {
  const ownerMap: Record<string, string> = {
    all_templates: 'All',
    mine: 'My',
    public: 'Public',
  };
  const typeMap: Record<string, string> = {
    alert: 'event alerts',
    static: 'static overlays',
    block: 'blocks',
  };

  const owner = ownerMap[filters.value.filter] ?? 'All';
  const type = typeMap[filters.value.type] ?? 'overlays';

  return `${owner} ${type}`;
});
const pageTitleString = pageTitle.value;

// Persist filter context so show/edit pages can build accurate breadcrumbs
watchEffect(() => {
  const params = new URLSearchParams();
  Object.entries(filters.value).forEach(([key, val]) => {
    if (val) params.set(key, String(val));
  });
  recordListContext({
    title: pageTitle.value,
    href: `${route('templates.index')}?${params.toString()}`,
  });
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: pageTitleString,
    href: '/templates',
  },
];
</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
          <Bell v-if="filters.type === 'alert'" class="mr-2 size-6" />
          <Blocks v-else-if="filters.type === 'block'" class="mr-2 size-6" />
          <Layers v-else class="mr-2 size-6" />
          <Heading :title="pageTitle" />
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto">
          <Link :href="route('builder.create')" class="btn btn-cancel">
            Builder
            <Blocks class="ml-2 h-4 w-4" />
          </Link>
          <Link :href="route('templates.create')" class="btn btn-primary">
            Create Overlay
            <PlusIcon class="ml-2 h-4 w-4" />
          </Link>
        </div>
      </div>

      <!-- Filters Section -->
      <FilterBar class="mb-4">
        <FilterSearchInput v-model="filters.search" label="Search title" placeholder="Search overlays and alerts..." @search="debounceSearch" />
        <FilterSelect v-model="filters.type" label="Type" select-id="filter-type" :options="typeOptions" @change="applyFilter" />
        <FilterSelect v-model="filters.filter" label="Ownership" select-id="filter-visibility" :options="ownerOptions" @change="applyFilter" />
        <FilterSelect v-model="filters.sort" label="Order" select-id="filter-sort" :options="sortOptions" @change="applyFilter" />
      </FilterBar>

      <TemplateCollection
        :templates="templates?.data ?? []"
        :show-owner="true"
        :show-event="filters.type === 'alert'"
        :current-user-id="currentUserId"
        empty-message="No overlays found. Try adjusting your filters or create a new overlay."
      />

      <!-- Pagination -->
      <div v-if="templates?.last_page > 1" class="mt-6">
        <Pagination :links="templates?.links" :from="templates?.from" :to="templates?.to" :total="templates?.total" />
      </div>
    </div>
  </AppLayout>
</template>
