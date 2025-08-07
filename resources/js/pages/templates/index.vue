// This would go in resources/js/Pages/Templates/Index.vue
<script setup lang="ts">
import { ref } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import debounce from "lodash/debounce"
import { EyeIcon, GitForkIcon, SplitIcon, PencilIcon, ExternalLinkIcon } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types/index.js';


const showFilters = ref(false);
const props = defineProps({
  templates: Object,
  filters: Object,
});

const filters = ref({
  filter: props.filters?.filter || '',
  search: props.filters?.search || '',
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

</script>

<template>
  <Head :title="'Overlabels Overlay Editor'" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="flex justify-between items-center mb-6">
        <Heading title="Your Templates" description="View, edit, fork your templates and create new ones." />
        <a
          :href="route('templates.create')"
          class="btn btn-primary"
        >
          Create New Template
        </a>
      </div>

      <!-- Filters -->
      <div class="mb-6 flex gap-4" v-if="showFilters">
        <select
          v-model="filters.filter"
          @change="applyFilter"
          class="rounded-2xl p-1 px-3 text-accent-foreground bg-accent"
        >
          <option value="" selected>All Templates</option>
          <option value="mine">My Templates</option>
          <option value="public">Public Templates</option>
        </select>

        <input
          v-model="filters.search"
          @input="debounceSearch"
          type="text"
          placeholder="Search templates..."
          class="rounded-md flex-1 border p-2 text-sm"
        />

        <select
          v-model="filters.sort"
          @change="applyFilter"
          class="rounded-md p-1 px-3 text-accent-foreground bg-accent"
        >
          <option value="created_at" selected>Date Created</option>
          <option value="name">Name</option>
          <option value="view_count">Views</option>
          <option value="fork_count">Forks</option>
        </select>
      </div>

      <!-- Template Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="template in templates?.data"
          :key="template.id"
          class="bg-accent/20 border rounded-2xl p-4"
        >

          <div class="flex justify-between items-center">
            <h3 class="font-semibold text-lg">{{ template.name }}</h3>
            <a
              v-if="template.is_public"
              class="btn btn-sm btn-cancel"
              :href="`/overlay/${template.slug}/public`"
              target="_blank"
            >
              Preview
              <ExternalLinkIcon class="w-4 h-4 ml-2" />
            </a>

          </div>
          <p class="text-sm mb-3">{{ template.description || 'No description' }}</p>

          <div class="bg-accent-foreground/5 p-0.5 px-2 rounded-2xl mb-3 text-sm inline-block">
            <span v-if="template.is_public" class="text-green-600">
              Public
            </span>
            <span v-else class="text-violet-400">
              Private
            </span>
          </div>
          <div class="flex items-center text-sm text-gray-500 mb-3">
            <img
              :src="template?.owner?.avatar"
              :alt="template?.owner?.name"
              class="w-6 h-6 rounded-full mr-2"
            />
            <span>{{ template.owner.name }}</span>
          </div>

          <div class="flex justify-between items-center text-sm">
            <div class="flex gap-4 text-gray-500">
              <span><EyeIcon class="w-4 h-4 inline-block" /> {{ template.view_count }}</span>
              <span><GitForkIcon class="w-4 h-4 inline-block" /> {{ template.forks_count }}</span>
            </div>

          </div>

          <div class="mt-4 flex gap-2">
            <Link
              :href="route('templates.show', template)"
              class="btn btn-primary btn-sm"
            >
              Details
              <EyeIcon class="w-4 h-4 ml-2" />
            </Link>
            <Link
              v-if="template.owner_id === $page.props.auth.user.id"
              :href="route('templates.edit', template)"
              class="btn btn-secondary btn-sm"
            >
              Edit
              <PencilIcon class="w-4 h-4 ml-2" />
            </Link>
            <button
              v-if="template.is_public || template.owner_id === $page.props.auth.user.id"
              @click="forkTemplate(template)"
              class="btn btn-warning btn-sm"
            >
              Fork
              <SplitIcon class="w-4 h-4 ml-2" />
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="templates?.last_page > 1" class="mt-6">
        <Pagination
          :links="templates?.links"
          :from="templates?.from"
          :to="templates?.to"
          :total="templates?.total"
        />
      </div>


    </div>
  </AppLayout>
</template>

