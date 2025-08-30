<script setup lang="ts">
import { ref } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import debounce from 'lodash/debounce';
import {
  EyeIcon,
  GitForkIcon,
  PencilIcon,
  ExternalLinkIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  GlobeIcon,
  LockIcon,
  BellIcon,
  Trash2Icon,
  MonitorIcon,
  PlusIcon,
  Building,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types/index.js';

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

const forkTemplate = async (template: any) => {
  if (!confirm(`Fork "${template.name}"?`)) return;

  try {
    await axios.post(route('templates.fork', template));
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to fork template:', error);
    alert('Failed to fork template');
  }
};

const deleteTemplate = async (template: any) => {
  if (!confirm(`Delete "${template.name}"?`)) return;

  try {
    await axios.delete('/templates/' + template.id);
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to delete template:', error);
    alert('Failed to delete template');
  }
};

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Overlabels Overlay Editor',
    href: '/templates',
  },
];

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

const eventTypeColors = {
  'channel.follow': 'bg-green-500',
  'channel.subscribe': 'bg-purple-500',
  'channel.subscription.gift': 'bg-pink-500',
  'channel.subscription.message': 'bg-indigo-500',
  'channel.cheer': 'bg-yellow-500',
  'channel.raid': 'bg-red-500',
  'channel.channel_points_custom_reward_redemption.add': 'bg-cyan-500',
  'stream.online': 'bg-green-400',
  'stream.offline': 'bg-red-400',
};

const eventTypeLabels = {
  'channel.follow': 'Follow',
  'channel.subscribe': 'Subscribe',
  'channel.subscription.gift': 'Gift Sub',
  'channel.subscription.message': 'Re-sub',
  'channel.cheer': 'Cheer',
  'channel.raid': 'Raid',
  'channel.channel_points_custom_reward_redemption.add': 'Points',
  'stream.online': 'Stream Online',
  'stream.offline': 'Stream Offline',
};

const getEventMapping = (template: any) => {
  if (!template.event_mappings || template.event_mappings.length === 0) return null;
  return template.event_mappings[0];
};
</script>

<template>
  <Head :title="'Overlabels Overlay Editor'" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Building class="w-6 h-6 mr-2" />
          <Heading title="All Templates" />
        </div>
        <Link :href="route('templates.create')" class="btn btn-primary">
          Create Template
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
              placeholder="Search templates..."
              class="h-[38px] w-full rounded-sm border border-sidebar bg-background px-3 py-1 text-foreground placeholder-muted-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
              id="filter-search"
            />
          </div>

          <div class="flex flex-col gap-1">
            <!-- Type Filter -->
            <label for="filter-type">Type</label>
            <select
              v-model="filters.type"
              @change="applyFilter"
              class="rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
              id="filter-type"
            >
              <option value="">All Types</option>
              <option value="static">Static Overlay</option>
              <option value="alert">Event Alert</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-visibility">Ownership</label>
            <!-- Visibility Filter -->
            <select
              v-model="filters.filter"
              @change="applyFilter"
              class="rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
              id="filter-visibility"
            >
              <option value="all_templates">All Templates</option>
              <option value="mine">My Templates</option>
              <option value="public">Public Templates</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label for="filter-sort">Order</label>
            <!-- Sort -->
            <select
              v-model="filters.sort"
              @change="applyFilter"
              class="rounded-sm border border-sidebar bg-background px-3 py-2 text-foreground focus:ring-1 focus:ring-primary/20 focus:outline-none"
              id="filter-sort"
            >
              <option value="created_at">Date Created</option>
              <option value="name">Name</option>
              <option value="view_count">Views</option>
              <option value="fork_count">Forks</option>
            </select>
          </div>
        </div>
      </div>


      <!-- Templates Table -->
      <div class="overflow-hidden rounded-sm border border-sidebar bg-sidebar-accent">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="border-b border-sidebar-foreground bg-sidebar">
              <tr>
                <th class="px-4 py-2 text-left">
                  <button
                    @click="sortBy('name')"
                    class="inline-flex cursor-pointer items-center gap-1 text-md font-medium text-muted-foreground hover:text-foreground"
                  >
                    Template
                    <component v-if="getSortIcon('name')" :is="getSortIcon('name')" class="h-4 w-4" />
                  </button>
                </th>
                <th class="px-3 py-2 text-left">
                  <span class="font-medium text-muted-foreground">Type</span>
                </th>
                <th class="px-3 py-2 text-left">
                  <span class="font-medium text-muted-foreground">Event</span>
                </th>
                <th class="px-3 py-2 text-left">
                  <span class="font-medium text-muted-foreground">Owner</span>
                </th>
                <th class="px-3 py-2 text-center">
                  <span class="font-medium text-muted-foreground">Stats</span>
                </th>
                <th class="px-3 py-2 text-left">
                  <button
                    @click="sortBy('created_at')"
                    class="inline-flex cursor-pointer items-center gap-1 font-medium text-muted-foreground hover:text-foreground"
                  >
                    Created
                    <component v-if="getSortIcon('created_at')" :is="getSortIcon('created_at')" class="h-4 w-4" />
                  </button>
                </th>
                <th class="px-3 py-2 text-left font-medium text-muted-foreground">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-sidebar">
              <tr v-for="template in templates?.data" :key="template.id" class="group hover:bg-sidebar">
                <td class="max-w-[400px] px-4 py-2">
                  <div>
                    <div class="relative flex items-center gap-2">
                      <h3 class="max-w-[250px] truncate text-sm font-medium text-foreground dark:text-foreground" :title="template.name">
                        {{ template.name }}
                      </h3>

                      <div class="absolute top-0 right-0 -mt-1 hidden items-center justify-end gap-1 p-2 px-4 group-hover:flex">
                        <Link
                          :href="route('templates.show', template)"
                          class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                          title="View Details"
                        >
                          <EyeIcon class="h-4 w-4" />
                        </Link>
                        <Link
                          v-if="template.owner_id === $page.props.auth.user.id"
                          :href="route('templates.edit', template)"
                          class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                          title="Edit"
                        >
                          <PencilIcon class="h-4 w-4" />
                        </Link>
                        <button
                          v-if="template.is_public || template.owner_id === $page.props.auth.user.id"
                          @click="forkTemplate(template)"
                          class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                          title="Fork"
                        >
                          <GitForkIcon class="h-4 w-4" />
                        </button>
                        <button
                          v-if="template.owner_id === $page.props.auth.user.id"
                          @click="deleteTemplate(template)"
                          class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                          title="Delete"
                        >
                          <Trash2Icon class="h-4 w-4" />
                        </button>
                        <a
                          v-if="template.is_public"
                          :href="`/overlay/${template.slug}/public`"
                          target="_blank"
                          class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                          title="Preview"
                        >
                          <ExternalLinkIcon class="h-4 w-4" />
                        </a>
                      </div>
                    </div>
                    <p class="mt-0.5 max-w-[350px] truncate text-xs text-muted-foreground">{{ template.description || 'No description' }}</p>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <div
                    class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-medium"
                    :class="
                      template.type === 'alert'
                        ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400'
                        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                    "
                  >
                    <BellIcon v-if="template.type === 'alert'" class="h-4 w-4" />
                    <MonitorIcon v-else class="h-4 w-4" />
                    <span class="hidden lg:inline">{{ template.type === 'alert' ? 'Alert' : 'Overlay' }}</span>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <div v-if="getEventMapping(template)" class="flex items-center gap-1">
                    <span :class="eventTypeColors[getEventMapping(template).event_type]" class="inline-block h-2 w-2 rounded-full"></span>
                    <span class="text-xs text-muted-foreground">
                      {{ eventTypeLabels[getEventMapping(template).event_type] }}
                    </span>
                  </div>
                  <span v-else class="text-xs text-muted-foreground">-</span>
                </td>
                <td class="px-3 py-2">
                  <div class="flex items-center gap-1.5">
                    <img :src="template?.owner?.avatar" :alt="template?.owner?.name" class="h-5 w-5 rounded-full" />
                    <span class="max-w-[100px] truncate text-xs text-foreground dark:text-foreground">{{ template.owner.name }}</span>
                  </div>
                </td>
                <td class="px-3 py-2 text-center">
                  <div class="flex items-center justify-center gap-2 text-xs text-muted-foreground">
                    <div class="flex items-center gap-0.5">
                      <EyeIcon class="h-4 w-4" />
                      <span>{{ template.view_count || 0 }}</span>
                    </div>
                    <div class="flex items-center gap-0.5">
                      <GitForkIcon class="h-4 w-4" />
                      <span>{{ template.forks_count || 0 }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <span class="text-xs text-muted-foreground">{{ formatDate(template.created_at) }}</span>
                </td>
                <td class="px-3 py-2">
                  <div class="inline-flex items-center gap-1">
                    <GlobeIcon v-if="template.is_public" class="h-4 w-4 text-green-500" title="Public" />
                    <LockIcon v-else class="h-4 w-4 text-violet-400" title="Private" />
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
        <Pagination :links="templates?.links" :from="templates?.from" :to="templates?.to" :total="templates?.total" />
      </div>
    </div>
  </AppLayout>
</template>
