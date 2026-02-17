<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref, computed } from 'vue';
import RekaToast from '@/components/RekaToast.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogClose } from '@/components/ui/dialog';

// Configure axios to include CSRF token and credentials
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// Get CSRF token from the meta-tag if it exists
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
}

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

const showDescription = ref(false);
const showUserTagInfo = ref(false);

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
      console.error('Error retrieving tags from api');
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

const copyAllTags = async () => {
  try {
    const allTags = tagList.value.map(tag => tag.display_tag).join(' ');
    await navigator.clipboard.writeText(allTags);
    toastMessage.value = `Copied ${tagList.value.length} tags to clipboard`;
    toastType.value = 'success';
    showToast.value = true;
  } catch (error) {
    console.error('Failed to copy:', error);
    toastMessage.value = 'Failed to copy tags';
    toastType.value = 'error';
    showToast.value = true;
  }
};

onMounted(() => {
  useGetTemplateTags();
});
</script>

<template>
  <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />

  <!-- Conditional Syntax Section -->
  <div class="mb-4 gap-2">
    <p>Template Tags are the bread and butter of Overlabels. These tags represent real Twitch data and you can easily use them in your HTML <strong>and</strong> CSS templates
      to create interactive overlays for your Twitch stream. This simple and easy to understand template syntax has been created to just work and mostly
    stay out of your way. Click a tag below to copy it to your clipboard.</p>

    <p class="pt-4 text-sm text-muted-foreground">
      Visit
      <a class="text-violet-400 hover:text-violet-800 dark:hover:text-violet-300" :href="route('help')" target="_blank">Help</a>
      to learn more about <strong>Dynamic & Conditional Template Syntax</strong>.
    </p>

  </div>

  <div class="flex gap-2 mb-4">
    <button @click.prevent="showUserTagInfo = true" class="cursor-pointer text-violet-400 border border-dotted p-2 hover:text-violet-800 dark:hover:text-violet-300">
      IMPORTANT INFO ABOUT <code>user_*</code> TAGS
    </button>
    <button @click.prevent="copyAllTags" class="cursor-pointer bg-violet-500 text-white border border-violet-600 px-4 py-2 rounded hover:bg-violet-600 dark:bg-violet-600 dark:hover:bg-violet-700">
      Copy All Tags
    </button>
  </div>
  <!-- Regular Template Tags -->
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    <div class="bg-sidebar-accent border border-sidebar rounded-sm p-4" v-for="(tags, category) in groupedTags" :key="category">
      <h2 class="mb-2 text-lg font-bold">{{ category }}</h2>

      <ul class="mb-4">
        <li v-for="tag in tags" :key="tag.display_tag">
          <button
            @click.prevent="copyTag(tag.display_tag)"
            class="cursor-pointer rounded bg-sidebar border border-card-foreground/20 hover:border-violet-300/40 hover:bg-violet-400/20 mb-1 px-1 py-0.5 text-sm"
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
  </div>
  <div
    v-if="tagList.length > 0"
    :class="{ 'text-violet-500 dark:text-violet-400': showDescription, 'text-accent-foreground': !showDescription }"
    class="flex items-center space-x-2 rounded text-accent-foreground/80"
  >
    <input type="checkbox" id="show-description" v-model="showDescription" class="mr-2 accent-violet-500" />
    <label for="show-description" class="cursor-pointer rounded p-1 text-sm select-none" :class="{ 'text-accent-foreground': showDescription }">
      Descriptions</label
    >
  </div>
  <div v-else>
    <p class="text-sm text-muted-foreground">No tags available</p>
  </div>


  <Dialog v-model:open="showUserTagInfo">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <div class="flex items-center space-x-2">
          <DialogTitle>[[[user_*]]] Tags</DialogTitle>
          <button @click.prevent="showUserTagInfo = false" class="rounded-full ml-auto w-[26px] h-[26px] cursor-pointer text-xl font-bold text-muted-foreground hover:text-muted-foreground hover:bg-accent">&times;</button>
        </div>
      </DialogHeader>
      <DialogDescription class="text-sm text-muted-foreground">
        <p><code>[[[user_*]]]</code> represents the most recent user who <strong>triggered an event</strong> on your stream. This is not your channel data.</p>
        <h2 class="text-lg mt-4 mb-2"><strong>For example:</strong></h2>
        <ul class="list-disc pl-5 mb-4">
          <li>Subscription → the subscriber's details</li>
          <li>Gift sub / cheer / follow → that user's details</li>
          <li>Default → your user account details</li>
        </ul>
        These tags are ideal when you want a dynamic, persistent and auto-updating reference to the last viewer who interacted with your stream.<br><br>
        <div class="bg-violet-100 border border-violet-400 text-violet-700 p-4 rounded relative dark:border-violet-500 dark:text-violet-800 dark:bg-violet-200">
          Do not use this tag to show your channel username. Use <strong>Channel Information</strong> tags if you want to show your channel info.<br><br>
          Currently your avatar is not in your channel data. In future updates this may happen if people want this.
        </div>
      </DialogDescription>
    </DialogContent>
  </Dialog>

</template>
