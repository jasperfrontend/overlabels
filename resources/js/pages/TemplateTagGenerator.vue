<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';
import { Copy, Eye, RefreshCw, Trash2, AlertCircle, Sparkles } from 'lucide-vue-next';

// Define interfaces for better TypeScript support
interface TemplateTag {
  id: number;
  tag_name: string;
  display_tag: string;
  display_name: string;
  description: string;
  data_type: string;
  sample_data: any;
  json_path: string;
}

interface CategoryData {
  category: {
    id: number;
    name: string;
    display_name: string;
    is_group: boolean;
  };
  tags: TemplateTag[];
}

interface TagPreview {
  tag: string;
  output: any;
  data_type: string;
  json_path: string;
}

// Props from the controller
const props = defineProps<{
  twitchData: Record<string, any>;
  existingTags: Record<string, CategoryData>;
  hasExistingTags: boolean;
  error?: string; // Added for error handling
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Template Tag Generator',
    href: '/tags-generator',
  },
];

// State with proper TypeScript types
const isGenerating = ref(false);
const tagPreviews = ref<Record<number, TagPreview>>({});
const isLoadingPreview = ref<Record<number, boolean>>({});
const generationError = ref('');
const posts = ref('');

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

// Computed
const organizedTags = computed(() => props.existingTags);

// Show an error message if there's an initial error
onMounted(async () => {
  if (props.error) {
    showToast.value = true;
    toastMessage.value = props.error;
    toastType.value = 'warning';
  }

  const path = window.location.pathname;
  const slug = path.replace(/^\/+|\/+$/g, '') || 'home'; // fallback

  const res = await fetch(`/${slug}`);
  if (res.ok) {
    posts.value = await res.json();
  }
});

// Generate template tags from current Twitch data
const generateTags = async () => {
  if (isGenerating.value) return;

  isGenerating.value = true;
  generationError.value = '';

  try {
    const response = await fetch('/template-tags/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    const data = await response.json();

    if (response.ok && data.success) {
      showToast.value = true;
      toastMessage.value = `Successfully generated ${data.generated} template tags!`;
      toastType.value = 'success';

      // Refresh the page to show new tags
      setTimeout(() => {
        router.reload({
          only: ['existingTags', 'hasExistingTags'],
        });
      }, 1000);
    } else {
      console.error('❌ Generation failed:', data);
      generationError.value = data.message || data.error || 'Unknown error occurred';

      showToast.value = true;
      toastMessage.value = `Failed to generate tags: ${generationError.value}`;
      toastType.value = 'error';
    }
  } catch (error) {
    console.error('💥 Generation error:', error);
    generationError.value = 'Network error - please check your connection and try again.';

    showToast.value = true;
    toastMessage.value = 'Failed to generate tags. Please try again.';
    toastType.value = 'error';
  } finally {
    isGenerating.value = false;
  }
};

// Clear all existing tags
const clearAllTags = async () => {
  if (!confirm('Are you sure you want to clear all template tags? This cannot be undone.')) {
    return;
  }

  try {
    const response = await fetch('/template-tags/clear', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    const data = await response.json();

    if (response.ok && data.success) {
      console.log('✅ Tags cleared:', data);

      showToast.value = true;
      toastMessage.value = data.message;
      toastType.value = 'info';

      router.reload();
    } else {
      console.error('❌ Clear failed:', data);

      showToast.value = true;
      toastMessage.value = `Failed to clear tags: ${data.message || data.error}`;
      toastType.value = 'error';
    }
  } catch (error) {
    console.error('💥 Clear error:', error);

    showToast.value = true;
    toastMessage.value = 'Failed to clear tags. Please try again.';
    toastType.value = 'error';
  }
};

// Clean up redundant _data_X_ tags
const cleanupRedundantTags = async () => {
  if (!confirm('Clean up redundant tags like "channel_followers_data_3_user_id"? This will remove all tags with _data_[number]* patterns.')) {
    return;
  }

  try {
    const response = await fetch('/template-tags/cleanup', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    const data = await response.json();

    if (response.ok && data.success) {
      console.log('✅ Tags cleaned up:', data);

      showToast.value = true;
      toastMessage.value = data.message;
      toastType.value = 'success';

      // Show deleted tags if user wants details
      if (data.deleted_count > 0) {
        console.log('Deleted tags:', data.deleted_tags);
      }

      router.reload();
    } else {
      console.error('❌ Cleanup failed:', data);

      showToast.value = true;
      toastMessage.value = `Failed to clean up tags: ${data.message || data.error}`;
      toastType.value = 'error';
    }
  } catch (error) {
    console.error('💥 Cleanup error:', error);

    showToast.value = true;
    toastMessage.value = 'Failed to clean up tags. Please try again.';
    toastType.value = 'error';
  }
};

// Preview a specific tag
const previewTag = async (tagId: number) => {
  if (isLoadingPreview.value[tagId]) return;

  // Set a loading state for this specific tag
  isLoadingPreview.value = { ...isLoadingPreview.value, [tagId]: true };

  try {
    const response = await fetch(`/template-tags/${tagId}/preview`);
    const data = await response.json();

    if (response.ok && !data.error) {
      tagPreviews.value = { ...tagPreviews.value, [tagId]: data };
    } else {
      console.error('Preview failed:', data);
      showToast.value = true;
      toastMessage.value = `Preview failed: ${data.message || data.error}`;
      toastType.value = 'error';
    }
  } catch (error) {
    console.error('Preview error:', error);
    showToast.value = true;
    toastMessage.value = 'Failed to load preview. Please try again.';
    toastType.value = 'error';
  } finally {
    // Clear loading state for this specific tag
    isLoadingPreview.value = { ...isLoadingPreview.value, [tagId]: false };
  }
};

// Clear a tag preview
const clearPreview = (tagId: number) => {
  // Remove the preview for this specific tag
  const { [tagId]: removed, ...rest } = tagPreviews.value;
  tagPreviews.value = rest;

  // Also clear the loading state if it exists
  const { [tagId]: removedLoading, ...restLoading } = isLoadingPreview.value;
  isLoadingPreview.value = restLoading;
};

// Hide toast
const hideToast = () => {
  showToast.value = false;
};
watch(toastMessage, () => {
  setTimeout(() => {
    showToast.value = false;
  }, 5000);
});

const copyTag = async (tagName: string) => {
  try {
    await navigator.clipboard.writeText(`[[[${tagName}]]]`);
    showToast.value = true;
    toastMessage.value = `Copied tag: ${tagName}`;
    toastType.value = 'info';
  } catch (error) {
    console.error('Failed to copy:', error);
    // Fallback for older browsers
    const textArea = document.createElement('textarea');
    textArea.value = `[[[${tagName}]]]`;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);

    showToast.value = true;
    toastMessage.value = `Copied tag: ${tagName}`;
    toastType.value = 'info';
  }
};

const slug = computed(() => {
  const path = window.location.pathname;
  return path.replace(/^\/+|\/+$/g, '') || 'home';
});

// Get style classes for data types
const getDataTypeClass = (dataType: string) => {
  switch (dataType) {
    case 'string':
      return 'bg-blue-100/50 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200';
    case 'integer':
    case 'float':
      return 'bg-green-100/50 text-green-800 dark:bg-green-900/50 dark:text-green-200';
    case 'boolean':
      return 'bg-purple-100/50 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200';
    case 'datetime':
      return 'bg-orange-100/50 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200';
    case 'url':
      return 'bg-yellow-100/50 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200';
    default:
      return 'bg-gray-100/50 text-gray-800 dark:bg-gray-900/50 dark:text-gray-200';
  }
};
</script>

<template>
  <Head title="Template Tags Generator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @update:visible="hideToast" />
    <div class="p-4">
      <div class="mb-6 flex items-center justify-between">
        <Heading title="Template Tags Generator" description="Generate template tags from Twitch data" />
        <div class="flex gap-2">
          <button @click="generateTags" :disabled="isGenerating" class="btn btn-primary">
            <RefreshCw v-if="isGenerating" class="h-4 w-4 animate-spin" />
            <RefreshCw v-else class="mr-3 h-4 w-4" />
            {{ isGenerating ? 'Generating...' : 'Generate Tags' }}
          </button>

          <button v-if="hasExistingTags" @click="cleanupRedundantTags" class="btn btn-warning">
            <Sparkles class="mr-3 h-4 w-4" />
            Clean Up Redundant
          </button>

          <button v-if="hasExistingTags" @click="clearAllTags" class="btn btn-danger">
            <Trash2 class="mr-3 h-4 w-4" />
            Clear All Tags
          </button>
        </div>
      </div>

      <!-- Error Display -->
      <div v-if="generationError" class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
        <div class="flex items-center gap-2">
          <AlertCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
          <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Generation Error</h3>
        </div>
        <p class="mt-2 text-sm text-red-700 dark:text-red-300">{{ generationError }}</p>
      </div>

      <!-- Existing Tags Display -->
      <div v-if="hasExistingTags" class="space-y-6">
        <div
          v-for="(categoryData, categoryName) in organizedTags"
          :key="categoryName"
          class="rounded-2xl border bg-accent/20 p-0 text-center transition"
        >
          <!-- Category Header -->
          <details class="group">
            <summary class="flex cursor-pointer list-none items-center justify-between rounded-2xl p-4 hover:bg-accent/50">
              <div class="flex items-center">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                  {{ categoryData.category.display_name }}
                </h3>
                <span class="ml-3 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                  {{ categoryData.tags.length }} tags
                </span>
              </div>
              <div class="transform transition-transform group-open:rotate-180">
                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </div>
            </summary>

            <!-- Tags Grid -->
            <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
              <div
                v-for="tag in categoryData.tags"
                :key="tag.id"
                class="rounded-lg border bg-accent p-4 text-left transition-all hover:border-accent hover:bg-accent/60 hover:shadow-sm dark:bg-accent/30 dark:hover:bg-accent/15"
              >
                <!-- Tag Header -->
                <div class="flex items-start justify-between">
                  <!-- Tag Info -->
                  <div class="min-w-0 flex-1">
                    <div class="mb-2 flex items-center">
                      <code
                        class="cursor-pointer rounded-none border border-dashed border-cyan-600/50 bg-gray-100 px-2.5 py-1.5 font-mono text-sm text-gray-800 transition hover:border-dashed hover:border-cyan-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                        title="click to copy tag"
                        @click="copyTag(tag.tag_name)"
                      >
                        {{ tag.display_tag }}
                      </code>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ tag.description }}</p>
                  </div>

                  <!-- Action Buttons -->
                  <div class="ml-3 flex items-center space-x-1">
                    <span
                      :class="getDataTypeClass(tag.data_type)"
                      class="mr-2 inline-block rounded px-2 py-1 text-xs font-medium"
                      :title="`json path: ${tag.json_path}`"
                    >
                      {{ tag.data_type }}
                    </span>
                    <!-- Copy Button -->
                    <button
                      @click="copyTag(tag.tag_name)"
                      class="cursor-pointer rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                      title="Copy tag"
                    >
                      <Copy class="h-4 w-4" />
                    </button>

                    <!-- Preview Button -->

                    <button
                      v-if="tagPreviews[tag.id]"
                      @click="clearPreview(tag.id)"
                      class="cursor-pointer rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>

                    <button
                      v-else
                      @click="previewTag(tag.id)"
                      :disabled="isLoadingPreview[tag.id]"
                      class="cursor-pointer rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                      title="Preview with real data"
                    >
                      <RefreshCw v-if="isLoadingPreview[tag.id]" class="h-4 w-4 animate-spin" />
                      <Eye v-else class="h-4 w-4" />
                    </button>


                  </div>
                </div>

                <!-- Preview Output -->
                <div v-if="tagPreviews[tag.id]" class="mt-3 rounded-lg border border-gray-200 bg-gray-100 p-3 dark:border-gray-700 dark:bg-gray-800">
                  <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Live Preview:</span>
                    <button @click="clearPreview(tag.id)" class="cursor-pointer transition hover:text-gray-600 dark:hover:text-gray-300">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="font-mono text-sm break-words text-gray-800 dark:text-gray-200">
                    {{ tagPreviews[tag.id].output }}
                  </div>
                </div>
              </div>
            </div>
          </details>
        </div>
      </div>
      <!-- No Tags State -->
      <div v-else class="py-12 text-center">
        <div class="mx-auto max-w-md">
          <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">No Template Tags Generated</h3>
          <p class="mb-4 text-gray-500 dark:text-gray-400">
            Click "Generate Tags" to analyze your Twitch data and create template tags automatically.
          </p>
          <button @click="generateTags" :disabled="isGenerating" class="btn btn-primary m-auto text-center">
            <RefreshCw v-if="isGenerating" class="mr-2 h-4 w-4 animate-spin" />
            <RefreshCw v-else class="mr-2 h-4 w-4" />
            {{ isGenerating ? 'Generating...' : 'Generate Tags Now' }}
          </button>
        </div>
      </div>

      <!-- Debug Info (only in development) -->
      <div class="mt-8 rounded-lg bg-gray-100 p-4 dark:bg-gray-800">
        <h3 class="mb-2 text-sm font-medium">Debug Information</h3>
        <details class="text-xs">
          <summary class="cursor-pointer">Raw Twitch Data Structure</summary>
          <pre class="mt-2 overflow-x-auto">{{ JSON.stringify(twitchData, null, 2) }}</pre>
        </details>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
