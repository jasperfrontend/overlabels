<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import UpdatesList from '@/components/UpdatesList.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import debounce from 'lodash/debounce';
import { Newspaper } from 'lucide-vue-next';
import type { BreadcrumbItem, Update, AppPageProps } from '@/types';

interface FiltersShape {
  search?: string;
  tag?: string;
  from?: string;
  to?: string;
}

interface Paginated<T> {
  data: T[];
  links: Array<{ url: string | null; label: string; active: boolean; page: number }>;
  from: number;
  to: number;
  total: number;
  last_page: number;
}

const props = defineProps<{
  updates: Paginated<Update>;
  filters?: FiltersShape;
  allTags: string[];
}>();

function normalizeFilters(input?: FiltersShape) {
  return {
    search: input?.search || '',
    tag: input?.tag || '',
    from: input?.from || '',
    to: input?.to || '',
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
  if (filters.value.tag) params.tag = filters.value.tag;
  if (filters.value.from) params.from = filters.value.from;
  if (filters.value.to) params.to = filters.value.to;
  return params;
}

const applyFilter = () => {
  router.get(route('updates.index'), buildQuery(), {
    preserveState: true,
    preserveScroll: true,
  });
};

const debounceSearch = debounce(() => applyFilter(), 300);

function clearTag() {
  filters.value.tag = '';
  applyFilter();
}

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.isAdmin);

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Updates', href: '/updates' },
];
</script>

<template>
  <Head title="Updates" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="mb-4 flex items-center gap-2">
        <Newspaper class="mr-2 size-6" />
        <Heading title="Updates" description="What's new on Overlabels - features, tips, kits and other goings-on." />
      </div>

      <div class="mb-4 rounded-sm border border-sidebar-border bg-sidebar-accent p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="flex flex-col gap-1">
            <label for="filter-search">Search</label>
            <input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search title and content..."
              class="input-border h-10 w-full rounded-sm"
              id="filter-search"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-tag">Tag</label>
            <select
              v-model="filters.tag"
              @change="applyFilter"
              class="input-border h-10 w-full rounded-sm"
              id="filter-tag"
            >
              <option value="">All tags</option>
              <option v-for="tag in props.allTags" :key="tag" :value="tag">{{ tag }}</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-from">From</label>
            <input
              v-model="filters.from"
              @change="applyFilter"
              type="date"
              class="input-border h-10 w-full rounded-sm"
              id="filter-from"
            />
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-to">To</label>
            <input
              v-model="filters.to"
              @change="applyFilter"
              type="date"
              class="input-border h-10 w-full rounded-sm"
              id="filter-to"
            />
          </div>
        </div>

        <div v-if="filters.tag" class="mt-3 text-xs text-muted-foreground">
          Filtering by tag: <span class="font-medium text-foreground">{{ filters.tag }}</span>
          <button type="button" @click="clearTag" class="ml-2 underline cursor-pointer">clear</button>
        </div>
      </div>

      <UpdatesList
        v-if="props.updates?.data?.length"
        :updates="props.updates.data"
        :is-admin="isAdmin"
      />

      <EmptyState v-else message="No updates yet. Check back soon." />

      <div v-if="props.updates?.last_page > 1" class="mt-6">
        <Pagination
          :links="props.updates.links"
          :from="props.updates.from"
          :to="props.updates.to"
          :total="props.updates.total"
        />
      </div>
    </div>
  </AppLayout>
</template>
