<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Copy, AlertCircle } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import Heading from '@/components/Heading.vue';
import GroupedCollection from '@/components/GroupedCollection.vue';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';
import type { CollectionGroup } from '@/types/collection';

interface TemplateTag {
  tag_name: string;
  display_tag: string;
  display_name: string;
  description: string;
  data_type: string;
  json_path: string;
  sample_data: unknown;
  is_live: boolean;
}

interface CategoryData {
  category: {
    name: string;
    display_name: string;
    description: string;
    is_group: boolean;
    sort_order: number;
  };
  tags: TemplateTag[];
}

// Every account gets the same catalogue, so there is nothing to generate and
// nothing to poll - the server builds this from a constant on each request and
// fills in this account's own current values.
const props = defineProps<{
  tags: Record<string, CategoryData>;
  liveValues: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Template Tags', href: '/tags' },
];

const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

// Keyed by display name on purpose: that is what the pre-component page
// persisted its expanded state under, so nobody's collapsed groups reset.
const groupedTags = computed<CollectionGroup<TemplateTag>[]>(() =>
  Object.values(props.tags)
    .filter((c) => c.tags.length > 0)
    .map((c) => ({
      key: c.category.display_name,
      label: c.category.display_name,
      items: c.tags,
    })),
);

function tagMatches(tag: TemplateTag, query: string): boolean {
  return tag.display_tag.toLowerCase().includes(query) || tag.tag_name.toLowerCase().includes(query) || tag.description.toLowerCase().includes(query);
}

// Only ever show a value this account actually has. The catalogue carries a
// static sample for each tag, but this page promises "your own values" - and a
// made-up one reads as real data. `goals_latest_target` would claim a goal of
// 2000 to someone with no goals, and overlay_name would claim "My Awesome
// Overlay". Nothing is the honest answer, and matches the house rule of
// rendering nothing rather than a placeholder.
function displayValue(tag: TemplateTag): string {
  if (!tag.is_live) return '';
  if (tag.sample_data === null || tag.sample_data === undefined || tag.sample_data === '') return '';
  if (typeof tag.sample_data === 'boolean') return tag.sample_data ? 'true' : 'false';
  return String(tag.sample_data);
}

async function copyTag(tag: TemplateTag) {
  try {
    await navigator.clipboard.writeText(tag.display_tag);
    toastMessage.value = `Copied ${tag.display_tag}`;
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Could not copy to clipboard';
    toastType.value = 'error';
  }
  showToast.value = true;
}
</script>

<template>
  <Head title="Template Tags" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <SettingsLayout>
      <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />

      <div class="flex flex-col gap-4">
        <Heading title="Template Tags" description="Drop any of these into an overlay. The values are your own, right now." />

        <div
          v-if="!liveValues"
          class="flex items-start gap-2 rounded-sm border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
        >
          <AlertCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
          <p class="text-sm text-foreground">
            Could not reach Twitch just now, so the values below are examples rather than your own data. The tags themselves are always available.
          </p>
        </div>

        <GroupedCollection
          :groups="groupedTags"
          :item-key="(tag) => tag.tag_name"
          :matches="tagMatches"
          storage-key="template_tags_page_expanded"
          noun="tag"
        >
          <template #item="{ item: tag }">
            <div
              class="row group/row collection-row flex cursor-pointer items-start justify-between gap-3 p-3 transition-all"
              role="button"
              tabindex="0"
              :title="`Click to copy ${tag.display_tag}`"
              @click="copyTag(tag)"
              @keydown.enter.prevent="copyTag(tag)"
            >
              <div class="min-w-0 flex-1">
                <span class="font-mono text-sm text-violet-700 dark:text-violet-300">{{ tag.display_tag }}</span>
                <p class="mt-1 text-sm text-foreground">{{ tag.description }}</p>
              </div>

              <div class="flex shrink-0 items-center gap-3">
                <span v-if="displayValue(tag)" class="max-w-[16rem] truncate font-mono text-sm text-foreground" :title="displayValue(tag)">
                  {{ displayValue(tag) }}
                </span>
                <Copy :size="15" class="shrink-0 text-muted-foreground group-hover/row:text-foreground" />
              </div>
            </div>
          </template>
        </GroupedCollection>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
