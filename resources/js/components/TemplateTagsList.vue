<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref, computed, watch } from 'vue';
import { Copy, Info } from '@lucide/vue';
import { useTemplateTagCatalogue, type TagCatalogue } from '@/composables/useTemplateTagCatalogue';
import RekaToast from '@/components/RekaToast.vue';
import GroupedCollection from '@/components/GroupedCollection.vue';
import type { CollectionGroup } from '@/types/collection';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

// XHR + withCredentials defaults are configured globally in app.ts.
// Add the CSRF token from the meta tag if it's present.
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
}

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

const showUserTagInfo = ref(false);

interface TemplateTag {
  display_tag: string;
  description: string;
  category?: string;
  data_type?: string;
}

// Tag selection modal state
const tagList = ref<TemplateTag[]>([]);
const categoryTags = ref<TagCatalogue>({});

// Categories to exclude - array data that doesn't render in templates
const HIDDEN_CATEGORIES = ['Other'];

// Fetch + cache live in the composable, shared with the editor's autocomplete.
const { catalogue, load: loadCatalogue } = useTemplateTagCatalogue();

function processTags(tags: TagCatalogue): void {
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

watch(catalogue, processTags, { immediate: true });

// Group tags by category. Keyed by the category name, which is what the
// pre-component list persisted its expanded state under.
const groupedTags = computed<CollectionGroup<TemplateTag>[]>(() => {
  const groups: Record<string, TemplateTag[]> = {};
  tagList.value.forEach((tag) => {
    if (tag.category) (groups[tag.category] ??= []).push(tag);
  });
  return Object.entries(groups).map(([category, tags]) => ({ key: category, label: category, items: tags }));
});

function tagMatches(tag: TemplateTag, query: string): boolean {
  return tag.display_tag.toLowerCase().includes(query) || tag.description.toLowerCase().includes(query);
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

const copyAllTags = async (visibleTags: TemplateTag[]) => {
  try {
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
  loadCatalogue();
});
</script>

<template>
  <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />

  <!-- Header section -->
  <div class="mb-5 space-y-3 pt-1">
    <p class="text-sm leading-relaxed text-foreground">
      Tags represent live Twitch data you can use in your HTML and CSS templates. Click any tag to copy it to your clipboard, then paste it into your
      template code. Visit
      <a
        class="font-medium text-violet-400 underline decoration-violet-400/30 underline-offset-2 hover:text-violet-300 hover:decoration-violet-300/50"
        :href="route('help.conditionals')"
        target="_blank"
        >Help</a
      >
      to learn about dynamic and conditional template syntax.
    </p>

    <!-- user_* info callout -->
    <button
      @click.prevent="showUserTagInfo = true"
      class="flex w-full cursor-pointer items-center gap-2.5 rounded-md border border-amber-500/30 bg-amber-500/5 px-3.5 py-2.5 text-left text-sm text-amber-400 transition-colors hover:border-amber-500/50 hover:bg-amber-500/10"
    >
      <Info :size="16" class="shrink-0" />
      <span
        ><code class="rounded bg-amber-500/10 px-1 py-0.5 text-xs font-semibold text-amber-300">user_*</code> tags show the last viewer who triggered
        an event - not your channel data. <strong>Click to read more!</strong></span
      >
    </button>
  </div>

  <TooltipProvider :delay-duration="150">
    <GroupedCollection
      :groups="groupedTags"
      :item-key="(tag) => tag.display_tag"
      :matches="tagMatches"
      storage-key="template_tags_expanded"
      noun="tag"
      group-noun="category"
      group-noun-plural="categories"
      items-class="flex flex-wrap gap-2"
      empty-message="No tags available"
    >
      <template #toolbar="{ items }">
        <button
          @click.prevent="copyAllTags(items)"
          class="flex h-8 shrink-0 cursor-pointer items-center gap-1.5 rounded-md border border-violet-500/30 bg-violet-500/10 px-3 text-xs font-medium text-violet-400 transition-colors hover:border-violet-500/50 hover:bg-violet-500/20"
        >
          <Copy :size="13" />
          Copy all
        </button>
      </template>

      <template #item="{ item: tag }">
        <Tooltip>
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
      </template>
    </GroupedCollection>
  </TooltipProvider>

  <!-- user_* info dialog -->
  <Dialog v-model:open="showUserTagInfo">
    <DialogContent class="max-w-lg">
      <DialogHeader>
        <DialogTitle><code>user_*</code> Tags</DialogTitle>
      </DialogHeader>
      <DialogDescription as="div" class="space-y-3 text-sm text-muted-foreground">
        <p>
          <code class="rounded bg-muted px-1 py-0.5 text-xs">user_*</code> represents the most recent user who
          <strong class="text-foreground">triggered an event</strong> on your stream.
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
        <p>These tags are ideal for a dynamic, persistent and auto-updating reference to the last viewer who interacted with your stream.</p>
        <div class="rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-400">
          Do not use this tag for your channel username. Use <strong>Channel Information</strong> tags for your own channel info.
        </div>
      </DialogDescription>
    </DialogContent>
  </Dialog>
</template>
