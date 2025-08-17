<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref, computed } from 'vue';
import RekaToast from '@/components/RekaToast.vue';

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

const showDescription = ref(false);

interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
}
interface CategoryTag {
  category?: {
    display_name: string;
    description?: string;
  };
  tags?: Array<{
    display_tag: string;
    description: string;
    sample_data?: string;
  }>;
  active_template_tags?: Array<{
    display_tag: string;
    description: string;
    sample_data: string;
  }>;
}

interface TagsResponse {
  tags: Record<string, CategoryTag>;
}

// Tag selection modal state
const tagList = ref<TemplateTag[]>([]);
const categoryTags = ref<Record<string, CategoryTag>>({});

// Cache configuration
const CACHE_KEY = 'template_tags_cache';
const CACHE_VERSION_KEY = 'template_tags_cache_version';
const CACHE_DURATION = 60 * 60 * 1000; // 1 hour in milliseconds
const CURRENT_CACHE_VERSION = 'v1';

interface CachedData {
  tags: Record<string, CategoryTag>;
  timestamp: number;
  version: string;
}

function getCachedTags(): CachedData | null {
  try {
    const cached = localStorage.getItem(CACHE_KEY);
    const version = localStorage.getItem(CACHE_VERSION_KEY);
    
    if (!cached || version !== CURRENT_CACHE_VERSION) {
      return null;
    }
    
    const data: CachedData = JSON.parse(cached);
    const now = Date.now();
    
    // Check if cache is expired
    if (now - data.timestamp > CACHE_DURATION) {
      localStorage.removeItem(CACHE_KEY);
      return null;
    }
    
    return data;
  } catch (error) {
    console.error('Error reading cache:', error);
    localStorage.removeItem(CACHE_KEY);
    return null;
  }
}

function setCachedTags(tags: Record<string, CategoryTag>): void {
  try {
    const cacheData: CachedData = {
      tags,
      timestamp: Date.now(),
      version: CURRENT_CACHE_VERSION,
    };
    localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData));
    localStorage.setItem(CACHE_VERSION_KEY, CURRENT_CACHE_VERSION);
  } catch (error) {
    console.error('Error setting cache:', error);
    // If localStorage is full or unavailable, continue without caching
  }
}

function processTags(tags: Record<string, CategoryTag>): void {
  // Store the categorized tags for the modal
  categoryTags.value = tags;

  // Flatten the tags for a simple list if needed
  const flattenedTags: TemplateTag[] = [];
  Object.entries(tags).forEach(([category, categoryData]) => {
    // Check for active_template_tags first, then fall back to tags
    const tagsArray = categoryData.active_template_tags || categoryData.tags;

    if (tagsArray && Array.isArray(tagsArray)) {
      tagsArray.forEach((tag) => {
        flattenedTags.push({
          display_tag: tag.display_tag,
          description: tag.description,
          category: categoryData.category?.display_name || category,
        });
      });
    }
  });

  tagList.value = flattenedTags;
}

function useGetTemplateTags(): void {
  // Check cache first
  const cached = getCachedTags();
  
  if (cached) {
    processTags(cached.tags);
    return;
  }
  
  // Fetch available tags from the API
  axios
    .get<TagsResponse>(route('tags.api.all'))
    .then((response) => {
      const tags = response.data.tags;
      
      // Cache the response
      setCachedTags(tags);
      
      // Process the tags
      processTags(tags);
    })
    .catch(() => {
      console.log('Error retrieving tags from api');
    });
}

// Group tags by category
const groupedTags = computed(() => {
  const groups: Record<string, TemplateTag[]> = {};
  tagList.value.forEach((tag) => {
    if (tag.category) {
      if (!groups[tag.category]) {
        groups[tag.category] = [];
      }
      groups[tag.category].push(tag);
    }
  });
  return groups;
});

const copyTag = async (tagName: string) => {
  try {
    await navigator.clipboard.writeText(tagName);
    toastMessage.value = `Copied "${tagName}" to clipboard`;
    toastType.value = 'success';
    showToast.value = true;
  } catch (error) {
    console.error('Failed to copy:', error);
    toastMessage.value = 'Failed to copy tag';
    toastType.value = 'error';
    showToast.value = true;
  }
};

onMounted(() => {
  useGetTemplateTags();
});
</script>

<template>
  <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" />
  <!--  <pre>{{groupedTags}}</pre>-->
  <div
    :class="{ 'bg-violet-500 dark:bg-violet-400': showDescription, 'bg-accent': !showDescription }"
    class="mb-4 flex items-center space-x-2 rounded text-accent-foreground/80"
  >
    <input type="checkbox" id="show-description" v-model="showDescription" class="mr-2 hidden" />
    <label for="show-description" class="block w-full cursor-pointer rounded p-2 text-sm select-none" :class="{ 'text-accent': showDescription }">{{
      showDescription ? 'Hide descriptions' : 'Show descriptions'
    }}</label>
  </div>
  <div v-for="(tags, category) in groupedTags" :key="category">
    <h2 class="mb-2 text-xl font-bold">{{ category }}</h2>
    <ul class="mb-4">
      <li v-for="tag in tags" :key="tag.display_tag">
        <button
          @click.prevent="copyTag(tag.display_tag)"
          class="cursor-pointer rounded bg-accent px-1 py-0.5 text-xs text-accent-foreground/80 transition-colors hover:bg-sidebar hover:text-accent-foreground"
          :title="`Click to copy ${tag.display_tag}`"
        >
          {{ tag.display_tag }}
        </button>
        <div v-if="showDescription" class="mb-1 text-xs text-muted-foreground">
          <span class="text-xs text-violet-500 dark:text-violet-400">{{ tag.description }}</span>
        </div>
      </li>
    </ul>
  </div>
</template>
