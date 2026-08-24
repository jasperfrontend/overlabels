<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Search, ChevronRight, ChevronsUpDown, ChevronsDownUp, Copy, AlertCircle } from '@lucide/vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';

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

interface TagGroup {
  label: string;
  description: string;
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

const groupedTags = computed<TagGroup[]>(() =>
  Object.values(props.tags)
    .filter((c) => c.tags.length > 0)
    .map((c) => ({
      label: c.category.display_name,
      description: c.category.description,
      tags: c.tags,
    })),
);

const totalTags = computed(() => groupedTags.value.reduce((s, g) => s + g.tags.length, 0));

const searchQuery = ref('');

const filteredGroupedTags = computed<TagGroup[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return groupedTags.value;

  return groupedTags.value
    .map((group) => ({
      label: group.label,
      description: group.description,
      tags: group.tags.filter(
        (tag) =>
          tag.display_tag.toLowerCase().includes(query) ||
          tag.tag_name.toLowerCase().includes(query) ||
          tag.description.toLowerCase().includes(query) ||
          group.label.toLowerCase().includes(query),
      ),
    }))
    .filter((g) => g.tags.length > 0);
});

const totalVisibleTags = computed(() => filteredGroupedTags.value.reduce((s, g) => s + g.tags.length, 0));

const EXPANDED_KEY = 'template_tags_page_expanded';

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
    localStorage.setItem(EXPANDED_KEY, JSON.stringify(expandedGroups.value));
  } catch {
    // ignore
  }
}

const expandedGroups = ref<Record<string, boolean>>(loadExpandedState());

function isGroupExpanded(label: string): boolean {
  return expandedGroups.value[label] ?? true;
}

function toggleGroup(label: string): void {
  expandedGroups.value[label] = !isGroupExpanded(label);
  saveExpandedState();
}

const allExpanded = computed(() => {
  return filteredGroupedTags.value.every((g) => isGroupExpanded(g.label));
});

function toggleAll(): void {
  const next = !allExpanded.value;
  filteredGroupedTags.value.forEach((g) => {
    expandedGroups.value[g.label] = next;
  });
  saveExpandedState();
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

        <!-- Search -->
        <div class="flex items-center gap-3">
          <div class="relative flex-1">
            <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
            <input v-model="searchQuery" placeholder="Filter tags..." class="input-border w-full py-1.5 pr-2.5 pl-8 text-sm" />
          </div>
        </div>

        <!-- Count + collapse/expand-all -->
        <div class="mb-3 flex items-center text-xs text-muted-foreground">
          <span v-if="searchQuery">
            {{ totalVisibleTags }} tag{{ totalVisibleTags !== 1 ? 's' : '' }} in {{ filteredGroupedTags.length }} group{{
              filteredGroupedTags.length !== 1 ? 's' : ''
            }}
          </span>
          <span v-else>
            {{ totalTags }} tag{{ totalTags !== 1 ? 's' : '' }} across {{ groupedTags.length }} group{{ groupedTags.length !== 1 ? 's' : '' }}
          </span>
          <button
            v-if="filteredGroupedTags.length > 0"
            class="ml-auto flex cursor-pointer items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            @click.prevent="toggleAll"
          >
            <ChevronsDownUp v-if="allExpanded" :size="13" />
            <ChevronsUpDown v-else :size="13" />
            {{ allExpanded ? 'Collapse all' : 'Expand all' }}
          </button>
        </div>

        <!-- No search results -->
        <div v-if="searchQuery && filteredGroupedTags.length === 0" class="py-8 text-center">
          <p class="text-sm text-muted-foreground">No tags match "{{ searchQuery }}"</p>
        </div>

        <!-- Collapsible groups -->
        <div class="space-y-1.5">
          <Collapsible
            v-for="group in filteredGroupedTags"
            :key="group.label"
            :open="isGroupExpanded(group.label)"
            @update:open="toggleGroup(group.label)"
          >
            <CollapsibleTrigger
              class="group collection-row flex w-full cursor-pointer items-center gap-2 px-2 py-4 text-left"
              :class="{ 'bg-sidebar-accent': isGroupExpanded(group.label) }"
            >
              <ChevronRight :size="14" class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90" />
              <span class="text-sm font-medium">{{ group.label }}</span>
              <span class="ml-auto bg-card px-2.5 py-1.5 text-xs">{{ group.tags.length }}</span>
            </CollapsibleTrigger>

            <CollapsibleContent>
              <div class="flex flex-col gap-2 bg-sidebar/50 p-4">
                <div
                  v-for="tag in group.tags"
                  :key="tag.tag_name"
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
              </div>
            </CollapsibleContent>
          </Collapsible>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
