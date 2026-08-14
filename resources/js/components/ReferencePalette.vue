<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { useHelpReference, type HelpEntry } from '@/composables/useHelpReference';
import { BookOpen, Search } from '@lucide/vue';

const { search } = useHelpReference();

const open = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

const results = computed<HelpEntry[]>(() => search(query.value, 40));

// Short snippet from body for preview, with query term bias if present.
function snippet(entry: HelpEntry): string {
  const body = entry.body
    .replace(/^#+\s+.*$/gm, '')
    .replace(/\s+/g, ' ')
    .trim();
  const q = query.value.trim().toLowerCase();
  if (q.length >= 2) {
    const idx = body.toLowerCase().indexOf(q);
    if (idx > 30) {
      const start = Math.max(0, idx - 30);
      return (start > 0 ? '...' : '') + body.slice(start, start + 140) + (body.length > start + 140 ? '...' : '');
    }
  }
  return body.slice(0, 140) + (body.length > 140 ? '...' : '');
}

watch(query, () => {
  selectedIndex.value = 0;
});

watch(open, (val) => {
  if (val) {
    query.value = '';
    selectedIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
  }
});

function navigate(entry: HelpEntry) {
  open.value = false;
  window.open(`/help/reference/${entry.category}/${entry.slug}`, '_blank', 'noopener,noreferrer');
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
    { description: 'Tags reference' },
  );
});
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="flex max-h-[85vh] max-w-xl flex-col gap-0 overflow-hidden bg-sidebar p-0" @interact-outside="open = false">
      <DialogTitle class="sr-only">Help reference search</DialogTitle>
      <div class="flex items-center gap-2 border-b border-sidebar-border px-3">
        <Search class="size-4 shrink-0 text-muted-foreground" />
        <input
          ref="inputRef"
          v-model="query"
          type="text"
          placeholder="Search tags, events, fields... (e.g. follower, raid, hype)"
          class="flex-1 bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground"
          @keydown="onKeydown"
        />
        <kbd class="rounded border border-sidebar-border px-1.5 py-0.5 text-[10px] text-muted-foreground/60">ESC</kbd>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto p-1">
        <div v-if="results.length === 0" class="p-6 text-center text-sm text-muted-foreground">Nothing matched "{{ query }}".</div>

        <button
          v-for="(entry, i) in results"
          :key="`${entry.category}/${entry.slug}`"
          :data-ref-palette-selected="i === selectedIndex"
          class="flex w-full cursor-pointer items-start gap-3 rounded-md px-3 py-2 text-left transition-colors"
          :class="i === selectedIndex ? 'bg-card text-accent-foreground' : 'text-foreground hover:bg-card'"
          @click="navigate(entry)"
          @mouseenter="selectedIndex = i"
        >
          <BookOpen class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="truncate font-mono text-sm">{{ entry.title }}</span>
              <span class="shrink-0 text-[10px] tracking-wide text-muted-foreground/70 uppercase">{{ entry.categoryLabel }}</span>
            </div>
            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ snippet(entry) }}</p>
            <div class="font-mono text-sm">{{ entry.tag }}</div>
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
