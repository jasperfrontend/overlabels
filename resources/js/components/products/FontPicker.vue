<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Check, ChevronDown, Search } from '@lucide/vue';

/**
 * The font row on the chat designer: a search over the whole Bunny Fonts
 * catalogue rather than the six families the overlay used to ship in its head.
 *
 * The catalogue is 118 KB and most visits never open this row, so it is
 * fetched once on first open and kept for the life of the page. Until it
 * arrives - and if it never does - the suggested six still work, so the row is
 * never dead.
 *
 * No per-row font preview on purpose. Drawing 1969 names in their own face
 * means 1969 stylesheet requests, and the page already has the honest preview:
 * the real overlay is on the right and repaints the moment a name is picked.
 */
const props = defineProps<{
  id: string;
  modelValue: string;
  suggested: { value: string; hint: string }[];
  catalogueUrl: string;
}>();

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

type Font = { value: string; category: string };

const open = ref(false);
const query = ref('');
const category = ref('');
const fonts = ref<Font[]>([]);
const loading = ref(false);
const failed = ref(false);
const root = ref<HTMLElement | null>(null);
const field = ref<HTMLInputElement | null>(null);

const hint = computed(() => props.suggested.find((s) => s.value === props.modelValue)?.hint ?? '');

const categories = computed(() => {
  const seen = new Set<string>();
  for (const font of fonts.value) seen.add(font.category);
  return [...seen].sort();
});

/**
 * Suggested first while the box is empty, then the rest of the catalogue.
 *
 * Promoted, never substituted: an earlier version RETURNED the six on an empty
 * query, which made "All" show six fonts and call them all of them. The
 * shortlist is a head start, so the list has to keep going past it.
 */
const results = computed<Font[]>(() => {
  const q = query.value.trim().toLowerCase();
  const pool = category.value ? fonts.value.filter((f) => f.category === category.value) : fonts.value;

  if (!q) {
    // A category filter is a deliberate narrowing, so the shortlist is only
    // promoted while no category is chosen - six sans-serif names have no
    // business heading a list of handwriting fonts.
    if (category.value) return pool.slice(0, LIMIT);

    const known = new Map(fonts.value.map((f) => [f.value, f]));
    const top = props.suggested.map((s) => known.get(s.value) ?? { value: s.value, category: '' });
    const names = new Set(top.map((f) => f.value));

    return [...top, ...pool.filter((f) => !names.has(f.value))].slice(0, LIMIT);
  }

  // Names starting with the query first, then names containing it, so typing
  // "inter" offers Inter before Interlaced-anything.
  const starts: Font[] = [];
  const contains: Font[] = [];
  for (const font of pool) {
    const name = font.value.toLowerCase();
    if (name.startsWith(q)) starts.push(font);
    else if (name.includes(q)) contains.push(font);
    if (starts.length >= LIMIT) break;
  }

  return [...starts, ...contains].slice(0, LIMIT);
});

// Enough to scroll through, few enough to render without virtualisation.
const LIMIT = 60;

// How many the current filter actually matches, so a capped list can say it is
// capped. A list that silently stops at 60 of 1969 is the same lie in a
// quieter voice.
const matchCount = computed(() => {
  const q = query.value.trim().toLowerCase();
  const pool = category.value ? fonts.value.filter((f) => f.category === category.value) : fonts.value;

  return q ? pool.filter((f) => f.value.toLowerCase().includes(q)).length : pool.length;
});

async function load() {
  if (fonts.value.length || loading.value) return;
  loading.value = true;

  try {
    const response = await fetch(props.catalogueUrl);
    if (!response.ok) throw new Error(String(response.status));
    const raw = (await response.json()) as Record<string, { name: string; category: string }>;
    fonts.value = Object.values(raw)
      .map((entry) => ({ value: entry.name, category: entry.category }))
      .sort((a, b) => a.value.localeCompare(b.value));
    failed.value = false;
  } catch {
    // The suggested six still render and still write, so a failed fetch
    // narrows the row rather than breaking it.
    failed.value = true;
  } finally {
    loading.value = false;
  }
}

async function toggle() {
  open.value = !open.value;
  if (!open.value) return;
  void load();
  await nextTick();
  field.value?.focus();
}

function choose(value: string) {
  emit('update:modelValue', value);
  open.value = false;
  query.value = '';
  category.value = '';
}

function onPointerDown(event: PointerEvent) {
  if (open.value && root.value && !root.value.contains(event.target as Node)) open.value = false;
}

watch(open, (isOpen) => {
  if (isOpen) document.addEventListener('pointerdown', onPointerDown);
  else document.removeEventListener('pointerdown', onPointerDown);
});

onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));
</script>

<template>
  <div ref="root" class="relative">
    <button
      :id="id"
      type="button"
      class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-sm border border-border bg-background px-2 py-1.5 text-left text-sm text-foreground"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggle"
    >
      <span class="truncate">{{ modelValue || 'Pick a font' }}</span>
      <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
    </button>

    <div v-if="open" class="absolute z-20 mt-1 flex max-h-80 w-full flex-col rounded-sm border border-border bg-background shadow-lg">
      <div class="flex items-center gap-2 border-b border-border px-2 py-1.5">
        <Search class="size-4 shrink-0 text-muted-foreground" />
        <input
          ref="field"
          v-model="query"
          type="search"
          class="w-full bg-transparent text-sm text-foreground outline-none"
          placeholder="Search every Bunny font"
          @keydown.escape="open = false"
        />
      </div>

      <div v-if="categories.length" class="flex flex-wrap gap-1 border-b border-border px-2 py-1.5">
        <button
          v-for="option in ['', ...categories]"
          :key="option || 'all'"
          type="button"
          class="cursor-pointer rounded-sm border px-1.5 py-0.5 text-xs"
          :class="category === option ? 'border-foreground text-foreground' : 'border-border text-muted-foreground hover:border-foreground/40'"
          @click="category = option"
        >
          {{ option || 'All' }}
        </button>
      </div>

      <ul class="overflow-y-auto" role="listbox">
        <li v-if="loading" class="px-2 py-2 text-xs text-muted-foreground">Loading the catalogue...</li>
        <li v-else-if="failed" class="px-2 py-2 text-xs text-muted-foreground">
          The font catalogue could not be loaded. The fonts below still work.
        </li>
        <li v-else-if="!results.length" class="px-2 py-2 text-xs text-muted-foreground">No font matches "{{ query }}".</li>

        <li v-for="font in results" :key="font.value" role="option" :aria-selected="font.value === modelValue">
          <button
            type="button"
            class="flex w-full cursor-pointer items-center justify-between gap-2 px-2 py-1.5 text-left text-sm text-foreground hover:bg-accent"
            @click="choose(font.value)"
          >
            <span class="truncate">{{ font.value }}</span>
            <Check v-if="font.value === modelValue" class="size-4 shrink-0" />
            <span v-else-if="font.category" class="shrink-0 text-xs text-muted-foreground">{{ font.category }}</span>
          </button>
        </li>

        <li v-if="results.length < matchCount" class="px-2 py-1.5 text-xs text-muted-foreground">
          Showing {{ results.length }} of {{ matchCount }}. Keep typing to narrow it down.
        </li>
      </ul>
    </div>

    <p v-if="hint && !open" class="mt-1.5 text-xs text-muted-foreground">{{ hint }}</p>
  </div>
</template>
