<script setup lang="ts" generic="TItem">
import { computed, ref } from 'vue';
import { ChevronRight, ChevronsDownUp, ChevronsUpDown, Search } from 'lucide-vue-next';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

export interface FilterableGroup<T> {
  /** Stable key for v-for and expanded-state persistence. */
  key: string;
  /** Display label shown in the group header. */
  label: string;
  /** Items in this group (rendered via the `item` slot). */
  items: T[];
  /**
   * Optional override of the count badge text. When omitted the badge
   * shows `items.length`. Use this when a group has a partial count like
   * "3/8 enabled".
   */
  badge?: string;
}

const props = withDefaults(
  defineProps<{
    groups: FilterableGroup<TItem>[];
    /**
     * Returns the searchable text for an item. Lowercased + substring
     * matched against the filter query.
     */
    itemSearchText: (item: TItem) => string;
    /** Singular/plural noun used in the counter line. */
    itemsLabel: { singular: string; plural: string };
    /** Filter input placeholder. */
    placeholder?: string;
    /**
     * localStorage key used to persist which groups are expanded. Omit
     * to keep expanded state in-memory only.
     */
    expandedStorageKey?: string;
    /**
     * Groups expand by default. Set to false to start collapsed.
     */
    defaultExpanded?: boolean;
    /**
     * Flat mode: no group headers, no collapsibles, just the items.
     * Filter bar and counter still render.
     */
    flat?: boolean;
  }>(),
  {
    placeholder: 'Filter...',
    defaultExpanded: true,
    flat: false,
  },
);

const searchQuery = ref('');

const filteredGroups = computed<FilterableGroup<TItem>[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return props.groups;

  return props.groups
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => {
        if (group.label.toLowerCase().includes(query)) return true;
        return props.itemSearchText(item).toLowerCase().includes(query);
      }),
    }))
    .filter((g) => g.items.length > 0);
});

const totalItems = computed(() => props.groups.reduce((s, g) => s + g.items.length, 0));
const visibleItems = computed(() => filteredGroups.value.reduce((s, g) => s + g.items.length, 0));

function loadExpandedState(): Record<string, boolean> {
  if (!props.expandedStorageKey) return {};
  try {
    const stored = localStorage.getItem(props.expandedStorageKey);
    if (stored) return JSON.parse(stored);
  } catch {
    // ignore
  }
  return {};
}

function saveExpandedState(): void {
  if (!props.expandedStorageKey) return;
  try {
    localStorage.setItem(props.expandedStorageKey, JSON.stringify(expandedGroups.value));
  } catch {
    // ignore
  }
}

const expandedGroups = ref<Record<string, boolean>>(loadExpandedState());

function isGroupExpanded(key: string): boolean {
  return expandedGroups.value[key] ?? props.defaultExpanded;
}

function toggleGroup(key: string): void {
  expandedGroups.value[key] = !isGroupExpanded(key);
  saveExpandedState();
}

const allExpanded = computed(() =>
  filteredGroups.value.length > 0 && filteredGroups.value.every((g) => isGroupExpanded(g.key)),
);

function toggleAll(): void {
  const next = !allExpanded.value;
  for (const g of filteredGroups.value) {
    expandedGroups.value[g.key] = next;
  }
  saveExpandedState();
}

const totalNoun = computed(() => (totalItems.value === 1 ? props.itemsLabel.singular : props.itemsLabel.plural));
</script>

<template>
  <div class="space-y-4">
    <!-- Description + actions -->
    <div v-if="$slots.description || $slots['header-actions']" class="flex items-start justify-between gap-3">
      <div v-if="$slots.description" class="flex-1 text-sm text-foreground">
        <slot name="description" />
      </div>
      <div v-if="$slots['header-actions']" class="shrink-0">
        <slot name="header-actions" />
      </div>
    </div>

    <!-- Filter bar -->
    <div v-if="totalItems > 0" class="relative">
      <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
      <input
        v-model="searchQuery"
        :placeholder="placeholder"
        class="input-border w-full py-1.5 pr-2.5 pl-8 text-sm"
      />
    </div>

    <!-- Counter + expand-all -->
    <div v-if="totalItems > 0" class="flex items-center text-xs text-muted-foreground">
      <span v-if="searchQuery">
        Showing {{ visibleItems }} of {{ totalItems }} {{ totalNoun }}
        <template v-if="!flat">
          in {{ filteredGroups.length }} group{{ filteredGroups.length !== 1 ? 's' : '' }}
        </template>
      </span>
      <span v-else>
        {{ totalItems }} {{ totalNoun }}
        <template v-if="!flat">
          across {{ groups.length }} group{{ groups.length !== 1 ? 's' : '' }}
        </template>
      </span>
      <button
        v-if="!flat && filteredGroups.length > 0"
        class="ml-auto flex cursor-pointer items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
        @click.prevent="toggleAll"
      >
        <ChevronsDownUp v-if="allExpanded" :size="13" />
        <ChevronsUpDown v-else :size="13" />
        {{ allExpanded ? 'Collapse all' : 'Expand all' }}
      </button>
    </div>

    <!-- Empty source -->
    <slot v-if="totalItems === 0" name="empty">
      <div class="rounded-sm border border-sidebar bg-sidebar-accent p-8 text-center text-sm text-muted-foreground">
        No {{ itemsLabel.plural }} yet.
      </div>
    </slot>

    <!-- No search results -->
    <template v-else-if="filteredGroups.length === 0">
      <slot name="empty-search" :query="searchQuery">
        <div class="py-8 text-center">
          <p class="text-sm text-muted-foreground">No {{ itemsLabel.plural }} match "{{ searchQuery }}"</p>
        </div>
      </slot>
    </template>

    <!-- Flat mode: just items -->
    <div v-else-if="flat" class="flex flex-col gap-2">
      <template v-for="group in filteredGroups" :key="group.key">
        <slot v-for="item in group.items" name="item" :item="item" :group="group" />
      </template>
    </div>

    <!-- Grouped mode: collapsibles -->
    <div v-else class="space-y-1.5">
      <Collapsible
        v-for="group in filteredGroups"
        :key="group.key"
        :open="isGroupExpanded(group.key)"
        @update:open="toggleGroup(group.key)"
      >
        <CollapsibleTrigger
          class="group flex w-full cursor-pointer items-center gap-2 rounded-md bg-sidebar px-2 py-4 text-left transition-colors hover:bg-sidebar-accent/50"
          :class="{ 'rounded-b-none bg-sidebar-accent/50 pb-0': isGroupExpanded(group.key) }"
        >
          <ChevronRight
            :size="14"
            class="shrink-0 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-90"
          />
          <slot name="group-icon" :group="group" />
          <span class="text-sm font-medium">{{ group.label }}</span>
          <span class="ml-auto bg-card px-2.5 py-1.5 text-xs">{{ group.badge ?? group.items.length }}</span>
        </CollapsibleTrigger>

        <CollapsibleContent>
          <div class="flex flex-col gap-2 bg-sidebar/50 p-4">
            <slot
              v-for="item in group.items"
              name="item"
              :item="item"
              :group="group"
            />
          </div>
        </CollapsibleContent>
      </Collapsible>
    </div>

    <!-- Footer slot -->
    <div v-if="$slots.footer">
      <slot name="footer" :total="totalItems" :visible="visibleItems" />
    </div>
  </div>
</template>
