<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { useHelpReference, type HelpHit } from '@/composables/useHelpReference';
import { docLabel, snippet } from '@/utils/helpSearch';
import { BookOpen, Search } from '@lucide/vue';

const { search, loading, failed, loadIndex } = useHelpReference();

const open = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

// `loading` is a dependency of search() only through the corpus it populates,
// which is a shallowRef the search index does not touch. Reading it here is
// what makes the list re-evaluate once the fetch lands.
//
// A result is a section of a page, or the page itself. The url carries the
// anchor, so opening it lands on the heading that answered the query.
const results = computed<HelpHit[]>(() => (loading.value ? [] : search(query.value, 40)));

watch(query, () => {
  selectedIndex.value = 0;
});

watch(open, (val) => {
  if (val) {
    query.value = '';
    selectedIndex.value = 0;
    void loadIndex();
    nextTick(() => inputRef.value?.focus());
  }
});

/**
 * Open in a new tab, deliberately. Help is server-rendered Blade rather than
 * part of the Inertia app, so router.visit would hit the 409 hard-load guard -
 * and the point of the palette is looking something up while you keep working
 * on the page you are on.
 */
function navigate(entry: HelpHit) {
  open.value = false;
  window.open(entry.url, '_blank', 'noopener,noreferrer');
}

function onKeydown(e: KeyboardEvent) {
  const total = results.value.length;
  if (!total) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value + 1) % total;
    scrollToSelected();
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value - 1 + total) % total;
    scrollToSelected();
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const item = results.value[selectedIndex.value];
    if (item) navigate(item);
  }
}

function scrollToSelected() {
  nextTick(() => {
    const el = document.querySelector('[data-ref-palette-selected="true"]');
    el?.scrollIntoView({ block: 'nearest' });
  });
}

const { register } = useKeyboardShortcuts();

onMounted(() => {
  register(
    'reference-palette',
    'alt+r',
    () => {
      open.value = !open.value;
    },
    { description: 'Search the docs' },
  );
});
</script>

<template>
  <Dialog v-model:open="open">
    <!--
      The height is FIXED, not content-driven. The dialog is vertically centered,
      so a height that tracks the result count makes the whole panel jump every
      time a query narrows the list. Only the list inside may grow and shrink.
    -->
    <DialogContent class="flex h-[min(640px,85vh)] max-w-xl flex-col gap-0 overflow-hidden bg-sidebar p-0" @interact-outside="open = false">
      <DialogTitle class="sr-only">Search the documentation</DialogTitle>
      <div class="flex items-center gap-2 border-b border-sidebar-border px-3">
        <Search class="size-4 shrink-0 text-muted-foreground" />
        <input
          ref="inputRef"
          v-model="query"
          type="text"
          placeholder="Search tutorials, guides and tags... (e.g. chat, follower, raid)"
          class="flex-1 bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground"
          @keydown="onKeydown"
        />
        <kbd class="rounded border border-sidebar-border px-1.5 py-0.5 text-[10px] text-muted-foreground/60">ESC</kbd>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto p-1">
        <div v-if="loading" class="p-6 text-center text-sm text-muted-foreground">Loading the docs...</div>

        <div v-else-if="failed" class="p-6 text-center text-sm text-muted-foreground">
          Could not load the docs index.
          <a href="/help" target="_blank" rel="noopener noreferrer" class="cursor-pointer text-violet-400 underline">Browse help instead</a>.
        </div>

        <div v-else-if="results.length === 0" class="p-6 text-center text-sm text-muted-foreground">Nothing matched "{{ query }}".</div>

        <button
          v-for="(entry, i) in results"
          :key="entry.url"
          :data-ref-palette-selected="i === selectedIndex"
          class="flex w-full cursor-pointer items-start gap-3 rounded-md px-3 py-2 text-left transition-colors"
          :class="i === selectedIndex ? 'bg-card text-accent-foreground' : 'text-foreground hover:bg-card'"
          @click="navigate(entry)"
          @mouseenter="selectedIndex = i"
        >
          <BookOpen class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="flex min-w-0 items-baseline gap-1.5 text-sm">
                <span class="max-w-[70%] shrink-0 truncate" :class="entry.doc.kind === 'reference' ? 'font-mono' : 'font-medium'">{{
                  entry.doc.title
                }}</span>
                <span v-if="entry.section?.heading" class="min-w-0 truncate text-muted-foreground">&rsaquo; {{ entry.section.heading }}</span>
              </span>
              <span class="shrink-0 text-[10px] tracking-wide text-muted-foreground/70 uppercase">{{ docLabel(entry.doc) }}</span>
            </div>
            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ snippet(entry, query) }}</p>
          </div>
        </button>
      </div>

      <div class="flex items-center gap-3 border-t border-sidebar-border px-3 py-2 text-[11px] text-muted-foreground/60">
        <span
          ><kbd class="rounded border border-sidebar-border px-1">&#8593;</kbd>
          <kbd class="rounded border border-sidebar-border px-1">&#8595;</kbd> navigate</span
        >
        <span><kbd class="rounded border border-sidebar-border px-1">Enter</kbd> open</span>
        <span><kbd class="rounded border border-sidebar-border px-1">Esc</kbd> close</span>
        <span class="ml-auto">{{ results.length }} results</span>
      </div>
    </DialogContent>
  </Dialog>
</template>
