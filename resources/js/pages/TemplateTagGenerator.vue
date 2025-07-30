<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Copy, Eye, RefreshCw, Trash2, AlertCircle } from 'lucide-vue-next';

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
    href: '/template-generator',
  },
];

// State with proper TypeScript types
const isGenerating = ref(false);
const tagPreviews = ref<Record<number, TagPreview>>({});
const isLoadingPreview = ref<Record<number, boolean>>({});
const generationError = ref('');

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

// Computed
const organizedTags = computed(() => props.existingTags);

// Show error message if there's an initial error
onMounted(() => {
  if (props.error) {
    showToast.value = true;
    toastMessage.value = props.error;
    toastType.value = 'warning';
  }
  
  console.log('🚀 Template Generator mounted');
  console.log('Twitch Data:', props.twitchData);
  console.log('Existing Tags:', props.existingTags);
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

// Keep tooltip visible when hovering over the tooltip itself
const keepTooltip = (tagId: number) => {
  // This prevents the tooltip from disappearing when the user moves
  // their mouse from the eye icon to the tooltip content
  // The tooltip will stay until they move away from both
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

// Get style classes for data types
const getDataTypeClass = (dataType: string) => {
  switch (dataType) {
    case 'string':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    case 'integer':
    case 'float':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    case 'boolean':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
    case 'datetime':
      return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
    case 'array':
    case 'array_count':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
  }
};
</script>

<template>
  <Head title="Template Generator" />
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
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Template Tag Generator</h1>
        
        <div class="flex items-center gap-2">
          <Button 
            @click="generateTags" 
            :disabled="isGenerating"
            variant="default"
            class="cursor-pointer"
          >
            <RefreshCw v-if="isGenerating" class="w-4 h-4 animate-spin" />
            <RefreshCw v-else class="w-4 h-4" />
            {{ isGenerating ? 'Generating...' : 'Generate Tags' }}
          </Button>
          
          <Button 
            v-if="hasExistingTags"
            @click="clearAllTags"
            variant="destructive"
            class="cursor-pointer"
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

      <!-- Information Card -->
      <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h2 class="text-lg font-semibold mb-2">How it works</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          This tool analyzes your current Twitch data and creates standardized template tags. 
          These tags can be used in your overlay templates to display dynamic information.
          Click "Generate Tags" to create tags based on your current Twitch data structure.
        </p>
      </div>

      <!-- Existing Tags Display -->
      <div v-if="hasExistingTags">
        <h2 class="text-xl font-semibold mb-4">Generated Template Tags</h2>
        
        <div v-for="(categoryData, categoryName) in organizedTags" :key="categoryName" 
             class="mb-6 bg-white dark:bg-gray-800 rounded-lg border p-4">
          <h3 class="text-lg font-medium mb-3 text-gray-900 dark:text-white">
            {{ categoryData.category.display_name }}
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div v-for="tag in categoryData.tags" :key="tag.id" 
                 class="border rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              
              <!-- Tag Header -->
              <div class="flex items-center justify-between mb-2">
                <code class="text-sm font-mono bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">
                  {{ tag.display_tag }}
                </code>
                
                <div class="flex items-center gap-1">
                  <!-- Copy Button -->
                  <button 
                    @click="copyTag(tag.tag_name)"
                    class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                    title="Copy tag"
                  >
                    <Copy class="w-3 h-3" />
                  </button>
                  
                  <!-- Preview Button -->
                  <button 
                    @click="previewTag(tag.id)"
                    :disabled="isLoadingPreview[tag.id]"
                    class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded"
                    title="Preview with real data"
                  >
                    <RefreshCw v-if="isLoadingPreview[tag.id]" class="w-3 h-3 animate-spin" />
                    <Eye v-else class="w-3 h-3" />
                  </button>
                </div>
              </div>
              
              <!-- Tag Info -->
              <div class="space-y-1">
                <div class="text-sm font-medium">{{ tag.display_name }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ tag.description }}</div>
                
                <!-- Data Type Badge -->
                <span :class="getDataTypeClass(tag.data_type)" 
                      class="inline-block px-2 py-1 text-xs font-medium rounded">
                  {{ tag.data_type }}
                </span>
                
                <!-- JSON Path -->
                <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                  {{ tag.json_path }}
                </div>
              </div>
              
              <!-- Preview Output -->
              <div v-if="tagPreviews[tag.id]" class="mt-2 p-2 bg-gray-50 dark:bg-gray-900 rounded text-xs">
                <div class="flex items-center justify-between mb-1">
                  <span class="font-medium text-gray-700 dark:text-gray-300">Live Preview:</span>
                  <button @click="clearPreview(tag.id)" class="text-gray-400 hover:text-gray-600">×</button>
                </div>
                <div class="font-mono text-gray-900 dark:text-gray-100">
                  {{ tagPreviews[tag.id].output }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Tags State -->
      <div v-else class="text-center py-12">
        <div class="max-w-md mx-auto">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Template Tags Generated</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-4">
            Click "Generate Tags" to analyze your Twitch data and create template tags automatically.
          </p>
          <Button @click="generateTags" :disabled="isGenerating" variant="default">
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
/* Add any component-specific styles here */
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