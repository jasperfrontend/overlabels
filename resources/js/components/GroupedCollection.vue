<script setup lang="ts" generic="TItem">
/**
 * The one grouped list. CollectionList is a flat run of rows; this is the
 * layer above it: a filter input, a "N things across M groups" line with
 * Expand / Collapse all, and one collapsible section per group with a count
 * pill. /tags is the reference implementation and looks identical to what it
 * looked like before this component existed.
 *
 * It replaced three hand-written copies of the same recipe (the /tags page,
 * TemplateTagsList, ControlsManager), each with its own search, its own
 * expand-all and its own localStorage key. Only the item itself is the
 * caller's: it goes in the `item` slot and can be a row, a chip, anything.
 *
 * Expanded state persists per `storageKey`, so a group you collapsed stays
 * collapsed next visit. Groups default to open.
 */
import { computed, ref } from 'vue';
import { Search, ChevronRight, ChevronsUpDown, ChevronsDownUp } from '@lucide/vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import EmptyState from '@/components/EmptyState.vue';
import type { CollectionGroup } from '@/types/collection';

const props = withDefaults(
  defineProps<{
    groups: CollectionGroup<TItem>[];
    /** Stable v-for key. */
    itemKey: (item: TItem) => string | number;
    /**
     * Does this item match the filter? `query` is already lowercased and
     * trimmed. The group label is matched by the component, so a caller only
     * has to look at the item.
     */
    matches: (item: TItem, query: string) => boolean;
    /** localStorage key for the expanded state. Unique per surface. */
    storageKey: string;
    /** What one item is called, for the count line and the placeholder. */
    noun?: string;
    nounPlural?: string;
    groupNoun?: string;
    groupNounPlural?: string;
    placeholder?: string;
    /** Layout of the items inside an open group. Rows by default; chips want `flex flex-wrap gap-2`. */
    itemsClass?: string;
    /** Shown when there are no items at all. Override with the `empty` slot. */
    emptyMessage?: string;
  }>(),
  {
    noun: 'item',
    nounPlural: undefined,
    groupNoun: 'group',
    groupNounPlural: undefined,
    placeholder: undefined,
    itemsClass: 'flex flex-col gap-2',
    emptyMessage: undefined,
  },
);

defineSlots<{
  /** One item. Rendered inside the group's items container. */
  item(props: { item: TItem; group: CollectionGroup<TItem> }): unknown;
  /** Sits to the right of the filter input. Receives the items the filter currently shows. */
  toolbar?(props: { items: TItem[] }): unknown;
  /** Replaces the default empty state when there are no items at all. */
  empty?(): unknown;
}>();

const plural = (n: number, one: string, many: string | undefined) => (n === 1 ? one : (many ?? `${one}s`));

const total = computed(() => props.groups.reduce((s, g) => s + g.items.length, 0));

const searchQuery = ref('');

const filteredGroups = computed<CollectionGroup<TItem>[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return props.groups.filter((g) => g.items.length > 0);

  return props.groups
    .map((group) => ({
      ...group,
      items: group.label.toLowerCase().includes(query) ? group.items : group.items.filter((item) => props.matches(item, query)),
    }))
    .filter((g) => g.items.length > 0);
});

const visibleItems = computed(() => filteredGroups.value.flatMap((g) => g.items));
const visible = computed(() => visibleItems.value.length);

const countLine = computed(() => {
  if (searchQuery.value) {
    const n = filteredGroups.value.length;
    return `${visible.value} ${plural(visible.value, props.noun, props.nounPlural)} in ${n} ${plural(n, props.groupNoun, props.groupNounPlural)}`;
  }
  const n = props.groups.filter((g) => g.items.length > 0).length;
  return `${total.value} ${plural(total.value, props.noun, props.nounPlural)} across ${n} ${plural(n, props.groupNoun, props.groupNounPlural)}`;
});

const inputPlaceholder = computed(() => props.placeholder ?? `Filter ${plural(2, props.noun, props.nounPlural)}...`);

// ---- Expanded state, persisted ----
function loadExpandedState(): Record<string, boolean> {
  try {
    const stored = localStorage.getItem(props.storageKey);
    if (stored) return JSON.parse(stored);
  } catch {
    // ignore
  }
  return {};
}

function saveExpandedState(): void {
  try {
    localStorage.setItem(props.storageKey, JSON.stringify(expanded.value));
  } catch {
    // ignore
  }
}

const expanded = ref<Record<string, boolean>>(loadExpandedState());

function isExpanded(key: string): boolean {
  return expanded.value[key] ?? true;
}

function toggle(key: string): void {
  expanded.value[key] = !isExpanded(key);
  saveExpandedState();
}

const allExpanded = computed(() => filteredGroups.value.every((g) => isExpanded(g.key)));

function toggleAll(): void {
  const next = !allExpanded.value;
  filteredGroups.value.forEach((g) => {
    expanded.value[g.key] = next;
  });
  saveExpandedState();
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <slot v-if="total === 0" name="empty">
      <EmptyState :message="emptyMessage" dashed />
    </slot>

    <template v-else>
      <!-- Filter + toolbar -->
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
          <input v-model="searchQuery" :placeholder="inputPlaceholder" class="input-border w-full py-1.5 pr-2.5 pl-8 text-sm" />
        </div>
        <slot name="toolbar" :items="visibleItems" />
      </div>

      <!-- Count + expand/collapse all -->
      <div class="mb-3 flex items-center text-xs text-muted-foreground">
        <span>{{ countLine }}</span>
        <button
          v-if="filteredGroups.length > 0"
          type="button"
          class="ml-auto flex cursor-pointer items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
          @click.prevent="toggleAll"
        >
          <ChevronsDownUp v-if="allExpanded" :size="13" />
          <ChevronsUpDown v-else :size="13" />
          {{ allExpanded ? 'Collapse all' : 'Expand all' }}
        </button>
      </div>

      <div v-if="searchQuery && filteredGroups.length === 0" class="py-8 text-center">
        <p class="text-sm text-muted-foreground">No {{ plural(2, noun, nounPlural) }} match "{{ searchQuery }}"</p>
      </div>

      <!-- One collapsible section per group -->
      <div class="space-y-1.5">
        <Collapsible v-for="group in filteredGroups" :key="group.key" :open="isExpanded(group.key)" @update:open="toggle(group.key)">
          <CollapsibleTrigger
            class="group collection-row flex w-full cursor-pointer items-center gap-2 px-2 py-4 text-left"
            :class="{ 'bg-sidebar-accent': isExpanded(group.key) }"
          >
            <ChevronRight :size="14" class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90" />
            <span class="text-sm font-medium">{{ group.label }}</span>
            <span class="ml-auto bg-card px-2.5 py-1.5 text-xs">{{ group.items.length }}</span>
          </CollapsibleTrigger>

          <CollapsibleContent>
            <div :class="itemsClass" class="bg-sidebar/50 p-4">
              <template v-for="item in group.items" :key="itemKey(item)">
                <slot name="item" :item="item" :group="group" />
              </template>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </div>
    </template>
  </div>
</template>
