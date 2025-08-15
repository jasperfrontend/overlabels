<script setup lang="ts">
import { ref } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import debounce from "lodash/debounce"
import {
  EyeIcon,
  GitForkIcon,
  PencilIcon,
  ExternalLinkIcon,
  SearchIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  GlobeIcon,
  LockIcon,
  BellIcon,
  MonitorIcon
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types/index.js';

const props = defineProps({
  templates: Object,
  filters: Object,
});

const filters = ref({
  filter: props.filters?.filter || '',
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

const sortBy = (field: string) => {
  if (filters.value.sort === field) {
    filters.value.direction = filters.value.direction === 'asc' ? 'desc' : 'asc';
  } else {
    filters.value.sort = field;
    filters.value.direction = 'desc';
  }
  applyFilter();
};

const getSortIcon = (field: string) => {
  if (filters.value.sort !== field) return null;
  return filters.value.direction === 'asc' ? ArrowUpIcon : ArrowDownIcon;
};

const forkTemplate = async (template:any) => {
  if (!confirm(`Fork "${template.name}"?`)) return;

  try {
    await axios.post(route('templates.fork', template));
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to fork template:', error);
    alert('Failed to fork template');
  }
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "Overlabels Overlay Editor",
    href: '/templates',
  },
];

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
};

</script>

<template>
  <Head :title="'Overlabels Overlay Editor'" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <Heading title="Your Templates" description="View, edit, fork your templates and create new ones." />
        <Link
          :href="route('templates.create')"
          class="btn btn-primary"
        >
          Create New Template
        </Link>
      </div>

      <!-- Filters Section -->
      <div class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="relative">
            <SearchIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search templates..."
              class="w-full pl-10 pr-3 py-2 bg-background dark:bg-background border border-border dark:border-border rounded-lg text-foreground dark:text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <!-- Type Filter -->
          <select
            v-model="filters.type"
            @change="applyFilter"
            class="px-3 py-2 bg-background dark:bg-background border border-border dark:border-border rounded-lg text-foreground dark:text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Types</option>
            <option value="overlay">Static Overlay</option>
            <option value="alert">Event Alert</option>
          </select>

          <!-- Visibility Filter -->
          <select
            v-model="filters.filter"
            @change="applyFilter"
            class="px-3 py-2 bg-background dark:bg-background border border-border dark:border-border rounded-lg text-foreground dark:text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="">All Templates</option>
            <option value="mine">My Templates</option>
            <option value="public">Public Templates</option>
          </select>

          <!-- Sort -->
          <select
            v-model="filters.sort"
            @change="applyFilter"
            class="px-3 py-2 bg-background dark:bg-background border border-border dark:border-border rounded-lg text-foreground dark:text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
          >
            <option value="created_at">Date Created</option>
            <option value="name">Name</option>
            <option value="view_count">Views</option>
            <option value="fork_count">Forks</option>
          </select>
        </div>
      </div>

      <!-- Templates Table -->
      <div class="bg-card dark:bg-card border border-border dark:border-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-muted dark:bg-muted border-b border-border dark:border-border">
              <tr>
                <th class="px-6 py-3 text-left">
                  <button
                    @click="sortBy('name')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                  >
                    Template
                    <component v-if="getSortIcon('name')" :is="getSortIcon('name')" class="w-3 h-3" />
                  </button>
                </th>
                <th class="px-6 py-3 text-left">
                  <span class="text-sm font-medium text-muted-foreground">Type</span>
                </th>
                <th class="px-6 py-3 text-left">
                  <span class="text-sm font-medium text-muted-foreground">Owner</span>
                </th>
                <th class="px-6 py-3 text-center">
                  <button
                    @click="sortBy('view_count')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                  >
                    Views
                    <component v-if="getSortIcon('view_count')" :is="getSortIcon('view_count')" class="w-3 h-3" />
                  </button>
                </th>
                <th class="px-6 py-3 text-center">
                  <button
                    @click="sortBy('fork_count')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                  >
                    Forks
                    <component v-if="getSortIcon('fork_count')" :is="getSortIcon('fork_count')" class="w-3 h-3" />
                  </button>
                </th>
                <th class="px-6 py-3 text-left">
                  <button
                    @click="sortBy('created_at')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                  >
                    Created
                    <component v-if="getSortIcon('created_at')" :is="getSortIcon('created_at')" class="w-3 h-3" />
                  </button>
                </th>
                <th class="px-6 py-3 text-right">
                  <span class="text-sm font-medium text-muted-foreground">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border dark:divide-border">
              <tr
                v-for="template in templates?.data"
                :key="template.id"
                class="hover:bg-muted/50 dark:hover:bg-muted/50 transition-colors"
              >
                <td class="px-6 py-4">
                  <div>
                    <div class="flex items-center gap-2">
                      <h3 class="font-medium text-foreground dark:text-foreground">{{ template.name }}</h3>
                      <GlobeIcon v-if="template.is_public" class="w-4 h-4 text-green-500" title="Public" />
                      <LockIcon v-else class="w-4 h-4 text-muted-foreground" title="Private" />
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">{{ template.description || 'No description' }}</p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                       :class="template.type === 'alert'
                         ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400'
                         : 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'">
                    <BellIcon v-if="template.type === 'alert'" class="w-3 h-3" />
                    <MonitorIcon v-else class="w-3 h-3" />
                    {{ template.type === 'alert' ? 'Alert' : 'Overlay' }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <img
                      :src="template?.owner?.avatar"
                      :alt="template?.owner?.name"
                      class="w-6 h-6 rounded-full"
                    />
                    <span class="text-sm text-foreground dark:text-foreground">{{ template.owner.name }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 text-center">
                  <span class="text-sm text-muted-foreground">{{ template.view_count || 0 }}</span>
                </td>
                <td class="px-6 py-4 text-center">
                  <span class="text-sm text-muted-foreground">{{ template.forks_count || 0 }}</span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-sm text-muted-foreground">{{ formatDate(template.created_at) }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <Link
                      :href="route('templates.show', template)"
                      class="p-1.5 text-muted-foreground hover:text-foreground transition-colors"
                      title="View Details"
                    >
                      <EyeIcon class="w-4 h-4" />
                    </Link>
                    <Link
                      v-if="template.owner_id === $page.props.auth.user.id"
                      :href="route('templates.edit', template)"
                      class="p-1.5 text-muted-foreground hover:text-foreground transition-colors"
                      title="Edit"
                    >
                      <PencilIcon class="w-4 h-4" />
                    </Link>
                    <button
                      v-if="template.is_public || template.owner_id === $page.props.auth.user.id"
                      @click="forkTemplate(template)"
                      class="p-1.5 text-muted-foreground hover:text-foreground transition-colors"
                      title="Fork"
                    >
                      <GitForkIcon class="w-4 h-4" />
                    </button>
                    <a
                      v-if="template.is_public"
                      :href="`/overlay/${template.slug}/public`"
                      target="_blank"
                      class="p-1.5 text-muted-foreground hover:text-foreground transition-colors"
                      title="Preview"
                    >
                      <ExternalLinkIcon class="w-4 h-4" />
                    </a>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="!templates?.data?.length" class="p-12 text-center">
          <p class="text-muted-foreground">No templates found. Try adjusting your filters or create a new template.</p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="templates?.last_page > 1" class="mt-6">
        <Pagination
          :links="templates?.links"
          :from="templates?.from"
          :to="templates?.to"
          :total="templates?.total"
          class="dark:text-foreground"
        />
      </div>
    </div>
  </AppLayout>
</template>

