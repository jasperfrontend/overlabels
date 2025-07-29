<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Copy, Eye, RefreshCw, Trash2 } from 'lucide-vue-next';

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

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

// Computed
const organizedTags = computed(() => props.existingTags);

// Generate template tags from current Twitch data
const generateTags = async () => {
  if (isGenerating.value) return;
  
  isGenerating.value = true;
  
  try {
    const response = await fetch('/template-tags/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });
    
    const data = await response.json();
    
    if (response.ok) {
      console.log('✅ Tags generated:', data);
      
      // Refresh the page to show new tags
      router.reload({
        only: ['existingTags', 'hasExistingTags']
      });
      
    } else {
      console.error('❌ Generation failed:', data);
      alert(`Failed to generate tags: ${data.message || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('💥 Generation error:', error);
    alert('Failed to generate tags. Please try again.');
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
    
    if (response.ok) {
      console.log('✅ Tags cleared:', data);
      router.reload();
    } else {
      console.error('❌ Clear failed:', data);
      alert(`Failed to clear tags: ${data.message || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('💥 Clear error:', error);
    alert('Failed to clear tags. Please try again.');
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
    
    if (response.ok) {
      tagPreviews.value = { ...tagPreviews.value, [tagId]: data };
    } else {
      console.error('Preview failed:', data);
    }
  } catch (error) {
    console.error('Preview error:', error);
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

onMounted(() => {
  console.log('🚀 Template Generator mounted');
  console.log('Twitch Data:', props.twitchData);
  console.log('Existing Tags:', props.existingTags);
});
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
            @click="clearAllTags" 
            :disabled="!hasExistingTags"
            variant="destructive"
            class="cursor-pointer"
          >
            <Trash2 class="w-4 h-4" />
            Clear All
          </Button>
        </div>
      </div>

      <!-- Info Panel -->
      <div class="rounded-lg border bg-muted/20 p-4">
        <p class="text-sm text-muted-foreground">
          This tool analyzes your Twitch API data and generates template tags that you can use in your OBS overlays. 
          Click "Generate Tags" to create tags from your current data, then use the tags on the left in your HTML templates.
        </p>
      </div>

      <!-- Main Content Grid -->
      <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Panel: Generated Template Tags -->
        <div class="rounded-lg border bg-background overflow-hidden">
          <div class="border-b bg-muted/50 p-4">
            <h2 class="text-xl font-semibold">Your Template Tags</h2>
            <p class="text-sm text-muted-foreground">
              Click any tag to copy it to your clipboard
            </p>
          </div>
          
          <div class="overflow-y-auto p-4">
            <div v-if="!hasExistingTags" class="text-center text-muted-foreground py-8">
              No template tags generated yet. Click "Generate Tags" to create them from your Twitch data.
            </div>
            
            <div v-else class="space-y-6">
              <div 
                v-for="(categoryData, categoryName) in organizedTags" 
                :key="categoryName"
                class="space-y-2"
              >
                <!-- Category Header -->
                <h3 class="text-lg font-semibold text-foreground">
                  {{ categoryData.category.display_name }}
                  <span 
                    v-if="categoryData.category.is_group"
                    class="ml-2 px-2 py-1 text-xs bg-amber-500 text-white rounded-full"
                  >
                    Group
                  </span>
                </h3>
                
                <!-- Category Tags -->
                <div class="space-y-1 ml-3">
                  <div 
                    v-for="tag in categoryData.tags" 
                    :key="tag.id"
                    class="group flex items-center justify-between p-2 rounded-lg border bg-card hover:bg-accent/50 transition-colors"
                  >
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <button
                          @click="copyTag(tag.tag_name)"
                          class="font-mono text-sm bg-muted px-2 py-1 rounded cursor-pointer hover:bg-muted/80"
                          :title="`Click to copy: ${tag.display_tag}`"
                        >
                          {{ tag.display_tag }}
                        </button>
                        
                        <span 
                          class="px-2 py-1 text-xs rounded-full"
                          :class="getDataTypeClass(tag.data_type)"
                        >
                          {{ tag.data_type }}
                        </span>
                      </div>
                      
                      <p class="text-sm text-muted-foreground mt-1">
                        {{ tag.display_name }}
                      </p>
                      
                      <!-- Sample Data Preview -->
                      <div v-if="tag.sample_data" class="text-xs text-muted-foreground mt-1">
                        Sample: {{ JSON.stringify(tag.sample_data).substring(0, 50) }}...
                      </div>
                    </div>
                    
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <!-- Preview with Tooltip -->
                      <div class="relative">
                        <Button
                          @mouseenter="previewTag(tag.id)"
                          @mouseleave="clearPreview(tag.id)"
                          variant="ghost"
                          size="sm"
                          class="cursor-pointer"
                          :title="'Hover to preview current output'"
                        >
                          <RefreshCw v-if="isLoadingPreview[tag.id]" class="w-3 h-3 animate-spin" />
                          <Eye v-else class="w-3 h-3" />
                        </Button>
                        
                        <!-- Tooltip positioned to the left -->
                        <div 
                          v-if="tagPreviews[tag.id] && !isLoadingPreview[tag.id]"
                          class="absolute right-full top-1/2 transform -translate-y-1/2 mr-2 px-3 py-2 bg-popover text-popover-foreground text-sm rounded-md shadow-lg border z-50 min-w-xs max-w-sm"
                          @mouseenter="keepTooltip(tag.id)"
                          @mouseleave="clearPreview(tag.id)"
                        >
                          <div class="font-medium text-xs text-muted-foreground mb-1">Current Output:</div>
                          <div class="font-mono break-words">{{ tagPreviews[tag.id].output }}</div>
                          <!-- Tooltip arrow pointing right -->
                          <div class="absolute left-full top-1/2 transform -translate-y-1/2 w-0 h-0 border-t-4 border-b-4 border-l-4 border-transparent border-l-popover"></div>
                        </div>
                      </div>
                      
                      <Button
                        @click="copyTag(tag.tag_name)"
                        variant="ghost"
                        size="sm"
                        class="cursor-pointer"
                      >
                        <Copy class="w-3 h-3" />
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Panel: JSON Data Viewer -->
        <div class="rounded-lg border bg-background overflow-hidden">
          <div class="border-b bg-muted/50 p-4">
            <h2 class="text-xl font-semibold">Your Twitch API Data</h2>
            <p class="text-sm text-muted-foreground">
              This is the data structure that template tags are generated from
            </p>
          </div>
          
          <div class="overflow-y-auto p-4">
            <pre class="text-xs whitespace-pre-wrap font-mono">{{ JSON.stringify(twitchData, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>