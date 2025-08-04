<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Copy, Eye, RefreshCw, Trash2, AlertCircle } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';

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

  const path = window.location.pathname
  const slug = path.replace(/^\/+|\/+$/g, '') || 'home' // fallback

  const res = await fetch(`/${slug}`)
  if (res.ok) {
    posts.value = await res.json()
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
      console.log('✅ Tags generated:', data);

      // Show success message
      showToast.value = true;
      toastMessage.value = `Successfully generated ${data.generated} template tags!`;
      toastType.value = 'success';

      // Refresh the page to show new tags
      setTimeout(() => {
        router.reload({
          only: ['existingTags', 'hasExistingTags']
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

// Preview a specific tag
const previewTag = async (tagId: number) => {
  if (isLoadingPreview.value[tagId]) return;

  // Set loading state for this specific tag
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

  // Also clear loading state if it exists
  const { [tagId]: removedLoading, ...restLoading } = isLoadingPreview.value;
  isLoadingPreview.value = restLoading;
};

// Hide toast
const hideToast = () => {
  showToast.value = false;
};

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
    <!-- Toast Component -->
    <RekaToast
      v-if="showToast"
      :message="toastMessage"
      :type="toastType"
      @update:visible="hideToast"
    />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <!-- Header Controls -->
      <div class="flex justify-between flex-col md:flex-row gap-4">
        <Heading title="Template Tags Generator" description="Generate template tags from Twitch data" />
        <div class="flex gap-2">
          <Button
            @click="generateTags"
            :disabled="isGenerating"
            variant="default"
            class="w-[50%] md:w-auto cursor-pointer rounded-2xl border bg-accent-foreground/60 p-4 text-center shadow backdrop-blur-sm transition hover:ring-2 hover:ring-gray-300 active:bg-accent dark:hover:ring-gray-700 dark:hover:bg-accent-foreground"
          >
            <RefreshCw v-if="isGenerating" class="w-4 h-4 animate-spin" />
            <RefreshCw v-else class="w-4 h-4" />
            {{ isGenerating ? 'Generating...' : 'Generate Tags' }}
          </Button>

          <Button
            v-if="hasExistingTags"
            @click="clearAllTags"
            variant="destructive"
            class="w-[50%] md:w-auto cursor-pointer rounded-2xl border bg-accent-foreground p-4 text-center shadow backdrop-blur-sm transition hover:ring-2 hover:ring-red-300 active:bg-accent dark:hover:ring-red-700"
          >
            <Trash2 class="w-4 h-4" />
            Clear All Tags
          </Button>
        </div>
      </div>

      <!-- Error Display -->
      <div v-if="generationError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
        <div class="flex items-center gap-2">
          <AlertCircle class="w-5 h-5 text-red-600 dark:text-red-400" />
          <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Generation Error</h3>
        </div>
        <p class="mt-2 text-sm text-red-700 dark:text-red-300">{{ generationError }}</p>
      </div>

      <!-- Existing Tags Display -->
      <div v-if="hasExistingTags" class="space-y-6">


        <div v-for="(categoryData, categoryName) in organizedTags" :key="categoryName"
            class="rounded-2xl border bg-accent/20 p-0 text-center shadow backdrop-blur-sm transition">

          <!-- Category Header -->
          <details class="group">
            <summary class="flex justify-between items-center p-4 cursor-pointer list-none hover:bg-accent/50 rounded-2xl">
              <div class="flex items-center">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                  {{ categoryData.category.display_name }}
                </h3>
                <span class="ml-3 px-2.5 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs font-medium rounded-full">
                  {{ categoryData.tags.length }} tags
                </span>
              </div>
              <div class="transform transition-transform group-open:rotate-180">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </div>
            </summary>

            <!-- Tags Grid -->
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="tag in categoryData.tags" :key="tag.id"
                  class="text-left border bg-accent dark:bg-accent/30 dark:hover:bg-accent/15 hover:bg-accent/60 rounded-lg p-4 transition-all hover:shadow-sm hover:border-accent ">

                <!-- Tag Header -->
                <div class="flex justify-between items-start">
                  <!-- Tag Info -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center mb-2">
                      <code class="text-sm font-mono bg-gray-100 dark:bg-gray-800 px-2.5 py-1.5 rounded-none text-gray-800 dark:text-gray-200 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-900 border border-dashed border-cyan-600/50 hover:border-dashed hover:border-cyan-900 transition"
                            title="click to copy tag"
                            @click="copyTag(tag.tag_name)">
                        {{ tag.display_tag }}
                      </code>

                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ tag.description }}</p>
                  </div>

                  <!-- Action Buttons -->
                  <div class="flex items-center ml-3 space-x-1">
                    <span :class="getDataTypeClass(tag.data_type)"
                          class="mr-2 inline-block px-2 py-1 text-xs font-medium rounded"
                          :title="`json path: ${tag.json_path}`">
                      {{ tag.data_type }}
                    </span>
                    <!-- Copy Button -->
                    <button @click="copyTag(tag.tag_name)"
                            class="p-2 rounded-lg cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                            title="Copy tag">
                      <Copy class="w-4 h-4" />
                    </button>

                    <!-- Preview Button -->
                    <button @click="previewTag(tag.id)"
                            :disabled="isLoadingPreview[tag.id]"
                            class="p-2 rounded-lg cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                            title="Preview with real data">
                      <RefreshCw v-if="isLoadingPreview[tag.id]" class="w-4 h-4 animate-spin" />
                      <Eye v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Preview Output -->
                <div v-if="tagPreviews[tag.id]" class="mt-3 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Live Preview:</span>
                    <button @click="clearPreview(tag.id)"
                            class="cursor-pointer transition hover:text-gray-600 dark:hover:text-gray-300">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                    </button>
                  </div>
                    <div class="font-mono text-sm text-gray-800 dark:text-gray-200 break-words">
                      {{ tagPreviews[tag.id].output }}
                    </div>
                </div>
              </div>
            </div>
          </details>
        </div>
      </div>
      <!-- No Tags State -->
      <div v-else class="text-center py-12">
        <div class="max-w-md mx-auto">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Template Tags Generated</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-4">
            Click "Generate Tags" to analyze your Twitch data and create template tags automatically.
          </p>
          <Button @click="generateTags" :disabled="isGenerating" variant="default" class="cursor-pointer">
            <RefreshCw v-if="isGenerating" class="w-4 h-4 mr-2 animate-spin" />
            <RefreshCw v-else class="w-4 h-4 mr-2" />
            {{ isGenerating ? 'Generating...' : 'Generate Tags Now' }}
          </Button>
        </div>
      </div>

      <!-- Debug Info (only in development) -->
      <div class="mt-8 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
        <h3 class="text-sm font-medium mb-2">Debug Information</h3>
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
