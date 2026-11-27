<script setup lang="ts" generic="TItem">
import { computed, nextTick, ref, watch } from 'vue';
import { Check, Plus, Search } from '@lucide/vue';

/**
 * "Active first": a card that lists only what is switched on, plus an Add
 * button that opens a picker over everything else - search, a grid of group
 * tiles with picked/total counts, and a group's items as tick buttons. The
 * Triggers and Targeting tabs of an alert are both this, so they look and
 * behave as one. The caller owns the state: the picker only asks whether an
 * item is picked and says when one is clicked.
 */
export interface PickerGroup<T> {
  key: string;
  title: string;
  /** Tiles are grouped under this heading, in the order groups first name it. */
  section: string;
  items: T[];
}

const props = withDefaults(
  defineProps<{
    groups: PickerGroup<TItem>[];
    itemKey: (item: TItem) => string;
    itemName: (item: TItem) => string;
    /** The monospace line under the name: an event key, an overlay slug. */
    itemSub: (item: TItem) => string;
    itemSearchText: (item: TItem) => string;
    isPicked: (item: TItem) => boolean;
    /** Shown on a search result, which has no group around it to say where it is from. */
    itemBadge?: (item: TItem) => string | undefined;
    addLabel: string;
    searchPlaceholder: string;
    backLabel: string;
    /** The line above a group's items once its tile is opened. */
    groupHint: (group: PickerGroup<TItem>) => string;
    /** Tile columns from `sm` up. Two below that. */
    tileColumns?: 4 | 6;
    disabled?: boolean;
  }>(),
  { tileColumns: 4, disabled: false, itemBadge: undefined },
);

const emit = defineEmits<{ toggle: [item: TItem] }>();

const SEARCH_LIMIT = 12;

const open = ref(false);
const query = ref('');
const drill = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const addButton = ref<HTMLButtonElement | null>(null);

function toggleOpen() {
  if (open.value) close();
  else open.value = true;
}

function close() {
  open.value = false;
  query.value = '';
  drill.value = '';
  nextTick(() => addButton.value?.focus());
}

watch(open, (isOpen) => {
  if (isOpen) nextTick(() => searchInput.value?.focus());
});

const tileGridClass = computed(() => (props.tileColumns === 6 ? 'grid-cols-3 sm:grid-cols-6' : 'grid-cols-2 sm:grid-cols-4'));

const sections = computed(() => {
  const out: { title: string; groups: { group: PickerGroup<TItem>; picked: number }[] }[] = [];
  for (const group of props.groups) {
    if (group.items.length === 0) continue;
    let section = out.find((s) => s.title === group.section);
    if (!section) {
      section = { title: group.section, groups: [] };
      out.push(section);
    }
    section.groups.push({ group, picked: group.items.filter((i) => props.isPicked(i)).length });
  }
  return out;
});

const drilledGroup = computed(() => (drill.value ? (props.groups.find((g) => g.key === drill.value) ?? null) : null));

const searchMatches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return [];
  return props.groups.flatMap((g) => g.items).filter((i) => props.itemSearchText(i).toLowerCase().includes(q));
});

const isSearching = computed(() => query.value.trim() !== '');

const shownItems = computed<TItem[]>(() => {
  if (isSearching.value) return searchMatches.value.slice(0, SEARCH_LIMIT);
  return drilledGroup.value?.items ?? [];
});

const itemsHint = computed(() => {
  if (isSearching.value) {
    const n = searchMatches.value.length;
    if (n > SEARCH_LIMIT) return `Showing ${SEARCH_LIMIT} of ${n} matches. Type more to narrow it down.`;
    return `${n} match${n === 1 ? '' : 'es'}`;
  }
  return drilledGroup.value ? props.groupHint(drilledGroup.value) : '';
});
</script>

<template>
  <section class="relative flex flex-col gap-4 border border-sidebar-border bg-card p-4 sm:p-5" @keydown.esc="open && close()">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-base font-semibold text-foreground"><slot name="title" /></h2>
        <div class="mt-0.5 text-xs text-muted-foreground"><slot name="subtitle" /></div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
        <button
          ref="addButton"
          type="button"
          class="btn btn-secondary btn-sm cursor-pointer"
          :aria-expanded="open"
          :disabled="disabled"
          @click="toggleOpen"
        >
          <Plus class="h-3.5 w-3.5" />
          {{ addLabel }}
        </button>
      </div>
    </div>

    <!-- The active list: whatever the caller renders for what is switched on. -->
    <slot />

    <div
      v-if="open"
      role="dialog"
      :aria-label="addLabel"
      class="flex flex-col gap-3.5 border border-violet-400/60 bg-background p-4 shadow-2xl md:absolute md:top-[4.25rem] md:right-5 md:z-20 md:w-[min(760px,calc(100%-2.5rem))]"
    >
      <div class="flex items-center gap-2.5">
        <button v-if="drilledGroup && !isSearching" type="button" class="btn btn-cancel btn-sm shrink-0 cursor-pointer" @click="drill = ''">
          {{ backLabel }}
        </button>
        <label class="relative block flex-1">
          <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <input
            ref="searchInput"
            v-model="query"
            type="text"
            :placeholder="searchPlaceholder"
            :aria-label="searchPlaceholder"
            class="input-border w-full py-2 pr-2.5 pl-8"
          />
        </label>
      </div>

      <!-- Tiles -->
      <div v-if="!isSearching && !drilledGroup" class="flex flex-col gap-3">
        <div v-for="section in sections" :key="section.title">
          <div class="mb-2 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">{{ section.title }}</div>
          <div class="grid gap-2" :class="tileGridClass">
            <button
              v-for="{ group, picked } in section.groups"
              :key="group.key"
              type="button"
              class="flex cursor-pointer items-center justify-between gap-2 border border-sidebar-border bg-sidebar-accent px-3 py-2.5 text-left text-sm font-semibold text-foreground transition-colors hover:border-[#1DAAFF]"
              @click="drill = group.key"
            >
              <span class="min-w-0 leading-tight break-words">{{ group.title }}</span>
              <span class="shrink-0 text-xs font-bold" :class="picked > 0 ? 'text-[#0A78BE] dark:text-[#7fd0ff]' : 'text-muted-foreground'">
                {{ picked }}/{{ group.items.length }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- A group's items, or search results -->
      <div v-if="shownItems.length > 0" class="flex flex-col gap-1.5">
        <div class="text-xs text-muted-foreground">{{ itemsHint }}</div>
        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
          <button
            v-for="item in shownItems"
            :key="itemKey(item)"
            type="button"
            :aria-pressed="isPicked(item)"
            class="flex min-h-11 w-full min-w-0 cursor-pointer items-center gap-2.5 border px-3 py-1.5 text-left text-sm transition-colors hover:border-[#1DAAFF]"
            :class="isPicked(item) ? 'border-[#1DAAFF] bg-[#1DAAFF]/10 text-foreground' : 'border-sidebar-border bg-sidebar-accent text-foreground'"
            @click="emit('toggle', item)"
          >
            <span
              class="inline-flex h-4 w-4 shrink-0 items-center justify-center border"
              :class="isPicked(item) ? 'border-[#1DAAFF] bg-[#1DAAFF]' : 'border-muted-foreground bg-background'"
            >
              <Check v-if="isPicked(item)" :size="11" :stroke-width="3.5" class="text-neutral-950" aria-hidden="true" />
            </span>
            <span class="flex min-w-0 flex-1 flex-col">
              <span class="truncate font-semibold">{{ itemName(item) }}</span>
              <span class="truncate font-mono text-[10.5px] text-muted-foreground">{{ itemSub(item) }}</span>
            </span>
            <span v-if="isSearching && itemBadge?.(item)" class="shrink-0 border border-violet-400/50 px-2 py-px text-[11px] text-muted-foreground">
              {{ itemBadge(item) }}
            </span>
          </button>
        </div>
      </div>

      <div v-else-if="isSearching" class="border border-dashed border-violet-400/50 p-5 text-center text-sm text-muted-foreground">
        Nothing matches that search.
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border pt-3">
        <span class="text-xs text-muted-foreground">The picker stays open so you can add several. Click a picked one to remove it.</span>
        <button type="button" class="btn btn-primary btn-sm cursor-pointer" @click="close">Done</button>
      </div>
    </div>
  </section>
</template>
