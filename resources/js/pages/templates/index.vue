<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, Link, Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import TemplateTable from '@/components/TemplateTable.vue';
import debounce from 'lodash/debounce';
import { PlusIcon, Building } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import type { BreadcrumbItem } from '@/types/index.js';
import type { AppPageProps } from '@/types';

const props = defineProps({
  templates: Object,
  filters: Object,
});

const filters = ref({
  filter: props.filters?.filter || 'all_templates',
  search: props.filters?.search || '',
  type: props.filters?.type || '',
  sort: props.filters?.sort || 'created_at',
  direction: props.filters?.direction || 'desc',
});

const applyFilter = () => {
  router.get(route('templates.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
};

const debounceSearch = debounce(() => {
  applyFilter();
}, 300);

const page = usePage<AppPageProps>();
const currentUserId = computed(() => page.props.auth.user.id);

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Overlabels Overlay Editor',
    href: '/templates',
  },
];

const pageTitle = computed(() => {
  const ownerMap: Record<string, string> = {
    all_templates: 'All',
    mine: 'My',
    public: 'Public',
  };
  const typeMap: Record<string, string> = {
    alert: 'event alerts',
    static: 'static overlays',
  };

  const owner = ownerMap[filters.value.filter] ?? 'All';
  const type = typeMap[filters.value.type] ?? 'overlays';

  return `${owner} ${type}`;
});
</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Building class="mr-2 h-6 w-6" />
          <Heading :title="pageTitle" />
        </div>
        <Link :href="route('templates.create')" class="btn btn-primary">
          Create overlay
          <PlusIcon class="ml-2 h-4 w-4" />
        </Link>
      </div>

      <!-- Filters Section -->
      <div class="mb-4 rounded-sm border border-sidebar bg-sidebar-accent p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4 lg:grid-cols-6">
          <!-- Search -->

          <div class="flex flex-col gap-1">
            <label for="filter-search">Search title</label>
            <input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search overlays and alerts..."
              class="input-border h-[40px] w-full rounded-sm"
              id="filter-search"
            />
          </div>

          <div class="flex flex-col gap-1">
            <!-- Type Filter -->
            <label for="filter-type">Type</label>
            <select
              v-model="filters.type"
              @change="applyFilter"
              class="input-border h-[40px] w-full rounded-sm"
              id="filter-type"
            >
              <option value="">All Types</option>
              <option value="static">Static overlay</option>
              <option value="alert">Event alert</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-visibility">Ownership</label>
            <!-- Visibility Filter -->
            <select
              v-model="filters.filter"
              @change="applyFilter"
              class="input-border h-[40px] w-full rounded-sm"
              id="filter-visibility"
            >
              <option value="all_templates">All overlays</option>
              <option value="mine">My overlays</option>
              <option value="public">Public overlays</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-sort">Order</label>
            <!-- Sort -->
            <select
              v-model="filters.sort"
              @change="applyFilter"
              class="input-border h-[40px] w-full rounded-sm"
              id="filter-sort"
            >
              <option value="created_at">Date created</option>
              <option value="name">Name</option>
              <option value="view_count">Views</option>
              <option value="fork_count">Forks</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Overlays Table -->
      <TemplateTable
        v-if="templates?.data?.length"
        :templates="templates.data"
        :show-owner="true"
        :show-event="filters.type === 'alert'"
        :current-user-id="currentUserId"
      />

      <!-- Empty State -->
      <div v-else class="rounded-sm border border-sidebar bg-sidebar-accent p-12 text-center">
        <p class="text-muted-foreground">No overlays found. Try adjusting your filters or create a new overlay.</p>
      </div>

      <!-- Pagination -->
      <div v-if="templates?.last_page > 1" class="mt-6">
        <Pagination :links="templates?.links" :from="templates?.from" :to="templates?.to" :total="templates?.total" />
      </div>
    </div>
  </AppLayout>
</template>
