// This would go in resources/js/Pages/Templates/Index.vue
<template>
  <AppLayout title="Templates">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6">
              <h2 class="text-xl font-semibold">Overlay Templates</h2>
              <Link
                :href="route('templates.create')"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
              >
                Create New Template
              </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex gap-4">
              <select
                v-model="filters.filter"
                @change="applyFilter"
                class="rounded-md border-gray-300"
              >
                <option value="">All Templates</option>
                <option value="mine">My Templates</option>
                <option value="public">Public Templates</option>
              </select>

              <input
                v-model="filters.search"
                @input="debounceSearch"
                type="text"
                placeholder="Search templates..."
                class="rounded-md border-gray-300 flex-1"
              />

              <select
                v-model="filters.sort"
                @change="applyFilter"
                class="rounded-md border-gray-300"
              >
                <option value="created_at">Date Created</option>
                <option value="name">Name</option>
                <option value="view_count">Views</option>
                <option value="fork_count">Forks</option>
              </select>
            </div>

            <!-- Template Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="template in templates.data"
                :key="template.id"
                class="border rounded-lg p-4 hover:shadow-lg transition-shadow"
              >
                <h3 class="font-semibold text-lg mb-2">{{ template.name }}</h3>
                <p class="text-gray-600 text-sm mb-3">{{ template.description || 'No description' }}</p>

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
                    <span>👁 {{ template.view_count }}</span>
                    <span>🍴 {{ template.forks_count }}</span>
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
                    class="text-blue-600 hover:text-blue-800 text-sm"
                  >
                    View
                  </Link>
                  <Link
                    v-if="template.owner_id === $page.props.auth.user.id"
                    :href="route('templates.edit', template)"
                    class="text-yellow-600 hover:text-yellow-800 text-sm"
                  >
                    Edit
                  </Link>
                  <button
                    v-if="template.is_public || template.owner_id === $page.props.auth.user.id"
                    @click="forkTemplate(template)"
                    class="text-green-600 hover:text-green-800 text-sm"
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
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { debounce } from 'lodash';

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
