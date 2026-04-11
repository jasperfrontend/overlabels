<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref, computed } from 'vue';
import { Search, Copy, Info, ChevronRight, ChevronsUpDown, ChevronsDownUp } from 'lucide-vue-next';
import RekaToast from '@/components/RekaToast.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

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

const showUserTagInfo = ref(false);
const searchQuery = ref('');

interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
  data_type?: string;
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
    data_type?: string;
  }>;
  active_template_tags?: Array<{
    display_tag: string;
    description: string;
    sample_data: string;
    data_type?: string;
  }>;
}

interface TagsResponse {
  tags: Record<string, CategoryTag>;
}

// Tag selection modal state
const tagList = ref<TemplateTag[]>([]);
const categoryTags = ref<Record<string, CategoryTag>>({});

// Categories to exclude - array data that doesn't render in templates
const HIDDEN_CATEGORIES = ['Other'];

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
  }
}

function processTags(tags: Record<string, CategoryTag>): void {
  categoryTags.value = tags;

  const flattenedTags: TemplateTag[] = [];
  Object.entries(tags).forEach(([category, categoryData]) => {
    const displayName = categoryData.category?.display_name || category;

    // Skip hidden categories
    if (HIDDEN_CATEGORIES.includes(displayName)) {
      return;
    }

    const tagsArray = categoryData.active_template_tags || categoryData.tags;

    if (tagsArray && Array.isArray(tagsArray)) {
      tagsArray.forEach((tag) => {
        flattenedTags.push({
          display_tag: tag.display_tag,
          description: tag.description,
          category: displayName,
          data_type: tag.data_type,
        });
      });
    }
  });

  tagList.value = flattenedTags;
}

function useGetTemplateTags(): void {
  const cached = getCachedTags();

  if (cached) {
    processTags(cached.tags);
    return;
  }

  axios
    .get<TagsResponse>(route('tags.api.all'))
    .then((response) => {
      const tags = response.data.tags;
      setCachedTags(tags);
      processTags(tags);
    })
    .catch(() => {
      console.error('Error retrieving tags from api');
    });
}

// Group tags by category, filtered by search
const filteredGroupedTags = computed(() => {
  const groups: Record<string, TemplateTag[]> = {};
  const query = searchQuery.value.toLowerCase().trim();

  tagList.value.forEach((tag) => {
    if (tag.category) {
      // Apply search filter
      if (query) {
        const matchesTag = tag.display_tag.toLowerCase().includes(query);
        const matchesDescription = tag.description.toLowerCase().includes(query);
        const matchesCategory = tag.category.toLowerCase().includes(query);
        if (!matchesTag && !matchesDescription && !matchesCategory) {
          return;
        }
      }

      if (!groups[tag.category]) {
        groups[tag.category] = [];
      }
      groups[tag.category].push(tag);
    }
  });

  return groups;
});

const totalVisibleTags = computed(() => {
  return Object.values(filteredGroupedTags.value).reduce((sum, tags) => sum + tags.length, 0);
});

const categoryCount = computed(() => {
  return Object.keys(filteredGroupedTags.value).length;
});

// Track which categories are expanded, persisted to localStorage
const EXPANDED_KEY = 'template_tags_expanded';

function loadExpandedState(): Record<string, boolean> {
  try {
    const stored = localStorage.getItem(EXPANDED_KEY);
    if (stored) return JSON.parse(stored);
  } catch {
    // ignore
  }
  return {};
}

function saveExpandedState(): void {
  try {
    localStorage.setItem(EXPANDED_KEY, JSON.stringify(expandedCategories.value));
  } catch {
    // ignore
  }
}

const expandedCategories = ref<Record<string, boolean>>(loadExpandedState());

function isCategoryExpanded(category: string): boolean {
  // Default to open if never toggled
  return expandedCategories.value[category] ?? true;
}

function toggleCategory(category: string): void {
  expandedCategories.value[category] = !isCategoryExpanded(category);
  saveExpandedState();
}

const allExpanded = computed(() => {
  return Object.keys(filteredGroupedTags.value).every((cat) => isCategoryExpanded(cat));
});

function toggleAll(): void {
  const newState = !allExpanded.value;
  Object.keys(filteredGroupedTags.value).forEach((cat) => {
    expandedCategories.value[cat] = newState;
  });
  saveExpandedState();
}

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
    const visibleTags = Object.values(filteredGroupedTags.value).flat();
    const allTags = visibleTags.map((tag) => tag.display_tag).join(' ');
    await navigator.clipboard.writeText(allTags);
    toastMessage.value = `Copied ${visibleTags.length} tags to clipboard`;
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

  <!-- Header section -->
  <div class="mb-5 pt-1 space-y-3">
    <p class="text-sm leading-relaxed text-foreground">
      Tags represent live Twitch data you can use in your HTML and CSS templates. Click any tag to copy it to your clipboard, then paste it into your template code.
      Visit
      <a class="font-medium text-violet-400 underline decoration-violet-400/30 underline-offset-2 hover:text-violet-300 hover:decoration-violet-300/50" :href="route('help.conditionals')" target="_blank">Help</a>
      to learn about dynamic and conditional template syntax.
    </p>

    <!-- user_* info callout -->
    <button
      @click.prevent="showUserTagInfo = true"
      class="flex w-full cursor-pointer items-center gap-2.5 rounded-md border border-amber-500/30 bg-amber-500/5 px-3.5 py-2.5 text-left text-sm text-amber-400 transition-colors hover:border-amber-500/50 hover:bg-amber-500/10"
    >
      <Info :size="16" class="shrink-0" />
      <span><code class="rounded bg-amber-500/10 px-1 py-0.5 text-xs font-semibold text-amber-300">user_*</code> tags show the last viewer who triggered an event - not your channel data. <strong>Click to read more!</strong></span>
    </button>
  </div>

  <!-- Search and actions bar -->
  <div class="mb-4 flex items-center gap-3">
    <div class="relative flex-1">
      <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
      <input
        v-model="searchQuery"
        placeholder="Filter tags..."
        class="input-border w-full pl-8 pr-2.5 py-1.5 text-sm"
      />
    </div>
    <button
      @click.prevent="copyAllTags"
      class="flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-md border border-violet-500/30 bg-violet-500/10 px-3 text-xs font-medium text-violet-400 transition-colors hover:border-violet-500/50 hover:bg-violet-500/20"
    >
      <Copy :size="13" />
      Copy all
    </button>
  </div>

  <!-- Tag count and collapse/expand toggle -->
  <div v-if="tagList.length > 0" class="mb-3 flex items-center text-xs text-muted-foreground">
    <span v-if="searchQuery">
      {{ totalVisibleTags }} tag{{ totalVisibleTags !== 1 ? 's' : '' }} in {{ categoryCount }} categor{{ categoryCount !== 1 ? 'ies' : 'y' }}
    </span>
    <span v-else>
      {{ tagList.length }} tags across {{ Object.keys(filteredGroupedTags).length }} categories
    </span>
    <button
      v-if="categoryCount > 0"
      class="ml-auto flex cursor-pointer items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
      @click.prevent="toggleAll"
    >
      <ChevronsDownUp v-if="allExpanded" :size="13" />
      <ChevronsUpDown v-else :size="13" />
      {{ allExpanded ? 'Collapse all' : 'Expand all' }}
    </button>
  </div>

  <!-- Categories with tags -->
  <TooltipProvider :delay-duration="150">
    <div v-if="Object.keys(filteredGroupedTags).length > 0" class="space-y-1.5">
      <Collapsible
        v-for="(tags, category) in filteredGroupedTags"
        :key="category"
        :open="isCategoryExpanded(String(category))"
        @update:open="toggleCategory(String(category))"
      >
        <CollapsibleTrigger
          class="group flex w-full cursor-pointer bg-sidebar items-center gap-2 rounded-md px-2 py-4 text-left transition-colors hover:bg-sidebar-accent/50"
          :class="{ 'bg-sidebar-accent/50 rounded-b-none pb-0': isCategoryExpanded(String(category)) }"
        >
          <ChevronRight
            :size="14"
            class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90"
          />
          <span class="text-sm font-medium">{{ category }}</span>
          <span class="ml-auto text-xs px-2.5 py-1.5 bg-card">{{ tags.length }}</span>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <div class="flex flex-wrap bg-sidebar/50 gap-2 p-4">
            <Tooltip v-for="tag in tags" :key="tag.display_tag">
              <TooltipTrigger as-child>
                <button
                  @click.prevent="copyTag(tag.display_tag)"
                  class="cursor-pointer rounded border border-sidebar-accent bg-card px-2 py-1 font-mono text-xs text-muted-foreground transition-all hover:border-violet-400/50 hover:bg-violet-500/10 hover:text-violet-300"
                  :title="`Click to copy ${tag.display_tag}`"
                >
                  {{ tag.display_tag }}
                </button>
              </TooltipTrigger>
              <TooltipContent side="bottom" :side-offset="6" class="max-w-64">
                <p>{{ tag.description }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Click to copy</p>
              </TooltipContent>
            </Tooltip>
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>

    <div v-else-if="searchQuery" class="py-8 text-center">
      <p class="text-sm text-muted-foreground">No tags match "{{ searchQuery }}"</p>
    </div>

    <div v-else>
      <p class="text-sm text-muted-foreground">No tags available</p>
    </div>
  </TooltipProvider>

  <!-- user_* info dialog -->
  <Dialog v-model:open="showUserTagInfo">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle><code>user_*</code> Tags</DialogTitle>
      </DialogHeader>
      <DialogDescription as="div" class="space-y-3 text-sm text-muted-foreground">
        <p>
          <code class="rounded bg-muted px-1 py-0.5 text-xs">user_*</code> represents the most recent user who <strong class="text-foreground">triggered an event</strong> on your stream.
          <span class="font-medium text-amber-400">This is not your channel data.</span>
        </p>
        <div>
          <p class="mb-1.5 font-medium text-foreground">For example:</p>
          <ul class="list-disc space-y-0.5 pl-5">
            <li>Subscription - the subscriber's details</li>
            <li>Gift sub / cheer / follow - that user's details</li>
            <li>Default - your user account details</li>
          </ul>
        </div>
        <p>
          These tags are ideal for a dynamic, persistent and auto-updating reference to the last viewer who interacted with your stream.
        </p>
        <div
          class="rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-400"
        >
          Do not use this tag for your channel username. Use <strong>Channel Information</strong> tags for your own channel info.
        </div>
      </DialogDescription>
    </DialogContent>
  </Dialog>
</template>
