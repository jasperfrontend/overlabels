// This would go in resources/js/Pages/Templates/Index.vue
<template>
  <AppLayout>

    <div class="overflow-hidden shadow-sm sm:rounded-lg">
      <div class="flex justify-between text-left p-4">

        <Heading title="Your Templates" description="View, edit, fork your templates and create new ones." />

        <Link
          :href="route('templates.create')"
          class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 pb-0 h-[42px] px-4 rounded inline-block"
        >
          Create New Template
        </Link>
      </div>

      <!-- Filters -->
      <div class="mb-6 p-4 flex gap-4">
        <select
          v-model="filters.filter"
          @change="applyFilter"
          class="rounded-md p-1 px-3 text-accent-foreground bg-accent"
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
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
        <div
          v-for="template in templates.data"
          :key="template.id"
          class="border rounded-lg p-4 hover:shadow-lg transition-shadow"
        >
          <h3 class="font-semibold text-lg mb-2">{{ template.name }}</h3>
          <p class="text-sm mb-3">{{ template.description || 'No description' }}</p>

          <div class="flex items-center text-sm text-gray-500 mb-3">
            <img
              :src="template.owner.avatar"
              :alt="template.owner.name"
              class="w-6 h-6 rounded-full mr-2"
            />
            <span>{{ template.owner.name }}</span>
          </div>

          <div class="flex justify-between items-center text-sm">
            <div class="flex gap-4 text-gray-500">
              <span><EyeIcon class="w-4 h-4 inline-block" /> {{ template.view_count }}</span>
              <span><GitForkIcon class="w-4 h-4 inline-block" /> {{ template.forks_count }}</span>
            </div>
            <div v-if="template.is_public" class="text-green-600">
              Public
            </div>
            <div v-else class="text-gray-500">
              Private
            </div>
          </div>

          <div class="mt-4 flex gap-2">
            <Link
              :href="route('templates.show', template)"
              class="bg-blue-600/50 border border-blue-600 hover:bg-blue-500/50 p-1 px-2 rounded-2xl text-sm cursor-pointer"
            >
              View
            </Link>
            <Link
              v-if="template.owner_id === $page.props.auth.user.id"
              :href="route('templates.edit', template)"
              class="bg-yellow-600/50 border border-yellow-600 hover:bg-yellow-500/50 p-1 px-2 rounded-2xl text-sm cursor-pointer"
            >
              Edit
            </Link>
            <button
              v-if="template.is_public || template.owner_id === $page.props.auth.user.id"
              @click="forkTemplate(template)"
              class="bg-green-600/50 border border-green-600 hover:bg-green-500/50 p-1 px-2 rounded-2xl text-sm cursor-pointer"
            >
              Fork
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="templates.last_page > 1" class="mt-6">
        <Pagination
          :links="templates.links"
          :from="templates.from"
          :to="templates.to"
          :total="templates.total"
        />
      </div>

    </div>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import debounce from "lodash/debounce"
import { EyeIcon, GitForkIcon } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';

const props = defineProps({
  templates: Object,
  filters: Object,
});

const filters = ref({
  filter: props.filters.filter || '',
  search: props.filters.search || '',
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc',
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

const forkTemplate = async (template) => {
  if (!confirm(`Fork "${template.name}"?`)) return;

  try {
    await axios.post(route('templates.fork', template));
    router.reload({ only: ['templates'] });
  } catch (error) {
    console.error('Failed to fork template:', error);
    alert('Failed to fork template');
  }
};
</script>
