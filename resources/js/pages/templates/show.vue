<template>
  <AppLayout :title="template.name">
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
              <div class="flex justify-between items-start">
                <div>
                  <h1 class="text-2xl font-bold">{{ template.name }}</h1>
                  <p v-if="template.description" class="text-gray-600 mt-2">
                    {{ template.description }}
                  </p>
                </div>
                <div class="flex space-x-2">
                  <Link
                    v-if="canEdit"
                    :href="route('templates.edit', template)"
                    class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600"
                  >
                    Edit
                  </Link>
                  <button
                    @click="forkTemplate"
                    class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600"
                  >
                    Fork
                  </button>
                  <button
                    v-if="canEdit"
                    @click="deleteTemplate"
                    class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600"
                  >
                    Delete
                  </button>
                </div>
              </div>

              <!-- Meta Information -->
              <div class="mt-4 flex items-center space-x-6 text-sm text-gray-600">
                <div class="flex items-center">
                  <img
                    :src="template.owner.avatar"
                    :alt="template.owner.name"
                    class="w-6 h-6 rounded-full mr-2"
                  />
                  <span>{{ template.owner.name }}</span>
                </div>
                <div>
                  <span class="font-medium">{{ template.view_count }}</span> views
                </div>
                <div>
                  <span class="font-medium">{{ template.forks_count }}</span> forks
                </div>
                <div v-if="template.fork_parent">
                  Forked from
                  <Link
                    :href="route('templates.show', template.fork_parent)"
                    class="text-blue-600 hover:underline"
                  >
                    {{ template.fork_parent.name }}
                  </Link>
                </div>
                <div>
                  <span
                    :class="template.is_public ? 'text-green-600' : 'text-gray-500'"
                    class="font-medium"
                  >
                    {{ template.is_public ? 'Public' : 'Private' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- URLs Section -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
              <h3 class="font-semibold mb-3">Overlay URLs</h3>
              <div class="space-y-3">
                <div>
                  <label class="text-sm text-gray-600">Public URL (Preview without data):</label>
                  <div class="flex items-center mt-1">
                    <input
                      :value="publicUrl"
                      readonly
                      class="flex-1 px-3 py-2 bg-white border border-gray-300 rounded-l-md text-sm"
                    />
                    <button
                      @click="copyUrl(publicUrl)"
                      class="px-4 py-2 bg-gray-600 text-white rounded-r-md hover:bg-gray-700 text-sm"
                    >
                      Copy
                    </button>
                  </div>
                </div>
                <div>
                  <label class="text-sm text-gray-600">Authenticated URL (Use with your token):</label>
                  <div class="flex items-center mt-1">
                    <input
                      :value="authUrl"
                      readonly
                      class="flex-1 px-3 py-2 bg-white border border-gray-300 rounded-l-md text-sm"
                    />
                    <button
                      @click="copyUrl(authUrl)"
                      class="px-4 py-2 bg-gray-600 text-white rounded-r-md hover:bg-gray-700 text-sm"
                    >
                      Copy
                    </button>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">
                    Replace YOUR_TOKEN_HERE with your actual access token
                  </p>
                </div>
              </div>
            </div>

            <!-- Code Tabs -->
            <div class="border rounded-lg overflow-hidden">
              <div class="flex border-b">
                <button
                  v-for="tab in ['html', 'css', 'js']"
                  :key="tab"
                  @click="activeTab = tab"
                  :class="[
                    'px-4 py-2 text-sm font-medium uppercase',
                    activeTab === tab
                      ? 'bg-gray-100 text-gray-900 border-b-2 border-blue-500'
                      : 'text-gray-600 hover:text-gray-900'
                  ]"
                >
                  {{ tab }}
                </button>
              </div>
              <div class="p-4 bg-gray-50">
                <pre class="overflow-x-auto"><code class="text-sm">{{ template[activeTab] || 'No content' }}</code></pre>
              </div>
            </div>

            <!-- Template Tags Used -->
            <div v-if="template.template_tags && template.template_tags.length > 0" class="mt-6">
              <h3 class="font-semibold mb-2">Template Tags Used</h3>
              <div class="flex flex-wrap gap-2">
                <code
                  v-for="tag in template.template_tags"
                  :key="tag"
                  class="px-2 py-1 bg-gray-100 rounded text-sm"
                >
                  {{ tag }}
                </code>
              </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-between">
              <Link
                :href="route('templates.index')"
                class="text-gray-600 hover:text-gray-900"
              >
                ← Back to Templates
              </Link>
              <div class="space-x-3">
                <a
                  :href="publicUrl"
                  target="_blank"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50"
                >
                  Preview Public
                  <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  template: Object,
  canEdit: Boolean,
});

const activeTab = ref('html');

const publicUrl = computed(() => {
  return `${window.location.origin}/overlay/${props.template.slug}/public`;
});

const authUrl = computed(() => {
  return `${window.location.origin}/overlay/${props.template.slug}#YOUR_TOKEN_HERE`;
});

const copyUrl = (url) => {
  navigator.clipboard.writeText(url);
  alert('URL copied to clipboard!');
};

const forkTemplate = async () => {
  if (!confirm('Fork this template?')) return;

  try {
    const response = await axios.post(route('templates.fork', props.template));
    router.visit(route('templates.show', response.data.template));
  } catch (error) {
    console.error('Failed to fork template:', error);
    alert('Failed to fork template');
  }
};

const deleteTemplate = () => {
  if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) return;

  router.delete(route('templates.destroy', props.template), {
    onSuccess: () => {
      // Will redirect to index
    },
  });
};
</script>
