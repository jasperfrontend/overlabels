<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import Modal from '@/components/Modal.vue';
import { Search, Loader2, Play, Pause, Plus, ExternalLink, X } from '@lucide/vue';

interface FreesoundHit {
  id: number;
  name: string;
  author: string;
  license: string;
  duration: number | null;
  preview_url: string | null;
  freesound_url: string | null;
  tags: string[];
}

const SORT_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'score', label: 'Relevance' },
  { value: 'duration_asc', label: 'Shortest first' },
  { value: 'duration_desc', label: 'Longest first' },
  { value: 'downloads_desc', label: 'Most downloaded' },
  { value: 'rating_desc', label: 'Highest rated' },
  { value: 'created_desc', label: 'Newest' },
];

// Cap tags-per-result so a sound with 30 tags doesn't blow up the row height.
// Most sounds have 4-8 tags so the cap rarely bites; when it does, the user
// can click through to Freesound for the full list.
const MAX_TAGS_PER_RESULT = 6;

interface FreesoundLibraryRow {
  id: number;
  freesound_id: number;
  name: string;
  author: string;
  license: string;
  duration: number | null;
  preview_url: string;
  freesound_url: string | null;
}

const props = defineProps<{
  show: boolean;
  libraryCount: number;
  libraryCap: number;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved', sound: FreesoundLibraryRow): void;
}>();

const query = ref('');
const sort = ref<string>('score');
const loading = ref(false);
const error = ref<string | null>(null);
const results = ref<FreesoundHit[]>([]);
const totalCount = ref(0);
const currentPage = ref(1);
const auditioningId = ref<number | null>(null);
const savingId = ref<number | null>(null);

// One shared audio element for in-modal auditioning, so clicking play on a new
// result automatically stops the previous one. Same singleton-cancel pattern
// the overlay renderer uses for alert sound playback.
let auditionPlayer: HTMLAudioElement | null = null;

watch(() => props.show, (visible) => {
  if (visible) {
    // Reset state when reopening so users don't see stale results.
    query.value = '';
    sort.value = 'score';
    results.value = [];
    totalCount.value = 0;
    currentPage.value = 1;
    error.value = null;
    stopAudition();
  } else {
    stopAudition();
  }
});

// Re-run the current query when the user picks a different sort. No-op if
// they haven't searched yet.
watch(sort, () => {
  if (query.value.trim() && results.value.length > 0) {
    runSearch(1);
  }
});

async function runSearch(page = 1) {
  if (!query.value.trim()) return;
  loading.value = true;
  error.value = null;
  stopAudition();

  try {
    const { data } = await axios.get(route('freesound.search'), {
      params: { q: query.value.trim(), page, sort: sort.value },
    });
    results.value = data.results ?? [];
    totalCount.value = data.count ?? 0;
    currentPage.value = page;
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Search failed.';
    results.value = [];
    totalCount.value = 0;
  } finally {
    loading.value = false;
  }
}

function searchTag(tag: string) {
  query.value = tag;
  runSearch(1);
}

function toggleAudition(hit: FreesoundHit) {
  if (!hit.preview_url) return;
  if (auditioningId.value === hit.id) {
    stopAudition();
    return;
  }
  stopAudition();
  auditionPlayer = new Audio(hit.preview_url);
  auditionPlayer.addEventListener('ended', stopAudition);
  auditionPlayer.play().catch(() => {
    // Autoplay-policy or network. Fall back to "stopped" state.
    stopAudition();
  });
  auditioningId.value = hit.id;
}

function stopAudition() {
  if (auditionPlayer) {
    auditionPlayer.pause();
    auditionPlayer.removeEventListener('ended', stopAudition);
    auditionPlayer = null;
  }
  auditioningId.value = null;
}

async function saveAndUse(hit: FreesoundHit) {
  if (savingId.value) return;
  savingId.value = hit.id;
  error.value = null;
  try {
    const { data } = await axios.post(route('freesound.save'), {
      freesound_id: hit.id,
    });
    emit('saved', data.sound as FreesoundLibraryRow);
    stopAudition();
    emit('close');
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Could not save sound.';
  } finally {
    savingId.value = null;
  }
}

function licenseShort(license: string): string {
  if (!license) return '';
  const l = license.toLowerCase();
  // URL forms (what the Freesound API actually returns)
  if (l.includes('publicdomain/zero') || l.includes('cc0')) return 'CC0';
  if (l.includes('/licenses/by-nc')) return 'CC-BY-NC';
  if (l.includes('/licenses/by-sa')) return 'CC-BY-SA';
  if (l.includes('/licenses/by-nd')) return 'CC-BY-ND';
  if (l.includes('sampling')) return 'Sampling+';
  if (l.includes('/licenses/by/')) return 'CC-BY';
  // Name-string forms (older Freesound docs claimed these)
  if (l.includes('creative commons 0')) return 'CC0';
  if (l === 'attribution') return 'CC-BY';
  if (l.includes('noncommercial')) return 'CC-BY-NC';
  // Long URLs we don't recognise - render an opaque badge rather than the URL.
  return l.startsWith('http') ? 'CC' : license;
}

function formatDuration(d: number | null): string {
  if (d === null || d === undefined) return '';
  if (d < 1) return `${Math.round(d * 1000)}ms`;
  return `${d.toFixed(1)}s`;
}
</script>

<template>
  <Modal :show="show" max-width="3xl" @close="emit('close')">
    <div class="p-6">
      <div class="mb-4 flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <h2 class="text-lg font-semibold text-accent-foreground">Browse Freesound</h2>
          <p class="text-sm text-foreground">
            Commercial-safe sounds only (CC0 and CC-BY). Library: {{ libraryCount }} / {{ libraryCap }}.
          </p>
        </div>
        <button
          type="button"
          class="cursor-pointer rounded-md p-1.5 text-foreground hover:bg-muted"
          aria-label="Close"
          @click="emit('close')"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <form @submit.prevent="runSearch(1)" class="mb-4 flex gap-2">
        <input
          v-model="query"
          type="search"
          placeholder="coin drop, bell, whoosh..."
          class="input-border flex-1"
          autofocus
        />
        <select
          v-model="sort"
          class="input-border cursor-pointer w-44"
          title="Sort results by"
        >
          <option v-for="opt in SORT_OPTIONS" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
        <button
          type="submit"
          :disabled="loading || !query.trim()"
          class="btn btn-primary cursor-pointer"
        >
          <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
          <Search v-else class="mr-2 h-4 w-4" />
          Search
        </button>
      </form>

      <div v-if="error" class="mb-4 rounded border border-red-400 bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950">
        {{ error }}
      </div>

      <div v-if="results.length === 0 && !loading && totalCount === 0" class="rounded border border-sidebar-border bg-muted/30 p-6 text-center text-sm text-foreground">
        Search Freesound for an alert sound. Try short, descriptive terms like "coin" or "ding".
      </div>

      <div v-if="results.length > 0" class="space-y-2 max-h-[60vh] overflow-y-auto pr-1">
        <div
          v-for="hit in results"
          :key="hit.id"
          class="flex items-center gap-3 rounded border border-sidebar-border bg-card p-3"
        >
          <button
            type="button"
            class="cursor-pointer rounded-full bg-violet-500/20 p-2 text-violet-600 hover:bg-violet-500/30 dark:text-violet-300"
            :disabled="!hit.preview_url"
            :title="auditioningId === hit.id ? 'Stop' : 'Play preview'"
            @click="toggleAudition(hit)"
          >
            <Pause v-if="auditioningId === hit.id" class="h-4 w-4" />
            <Play v-else class="h-4 w-4" />
          </button>

          <div class="flex-1 min-w-0">
            <div class="truncate text-sm font-medium text-accent-foreground">{{ hit.name }}</div>
            <div class="text-xs text-foreground/80 flex items-center gap-2 flex-wrap">
              <span>by {{ hit.author }}</span>
              <span class="inline-flex items-center rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase">
                {{ licenseShort(hit.license) }}
              </span>
              <span v-if="hit.duration !== null">{{ formatDuration(hit.duration) }}</span>
              <a
                v-if="hit.freesound_url"
                :href="hit.freesound_url"
                target="_blank"
                rel="noopener noreferrer"
                class="cursor-pointer inline-flex items-center gap-0.5 text-violet-500 hover:underline"
              >
                Page <ExternalLink class="h-3 w-3" />
              </a>
            </div>
            <div v-if="hit.tags && hit.tags.length" class="mt-1 flex flex-wrap gap-1">
              <button
                v-for="tag in hit.tags.slice(0, MAX_TAGS_PER_RESULT)"
                :key="tag"
                type="button"
                class="cursor-pointer rounded bg-muted/60 px-1.5 py-0.5 text-[10px] text-foreground hover:bg-violet-500/20 hover:text-violet-700 dark:hover:text-violet-300"
                :title="`Search for: ${tag}`"
                @click="searchTag(tag)"
              >{{ tag }}</button>
            </div>
          </div>

          <button
            type="button"
            :disabled="savingId === hit.id || !hit.preview_url"
            class="btn btn-primary btn-sm cursor-pointer"
            @click="saveAndUse(hit)"
          >
            <Loader2 v-if="savingId === hit.id" class="mr-1 h-4 w-4 animate-spin" />
            <Plus v-else class="mr-1 h-4 w-4" />
            Use & save
          </button>
        </div>
      </div>

      <div class="mt-4 flex items-center justify-between gap-3">
        <div v-if="totalCount > 15" class="flex items-center gap-2 text-sm text-foreground">
          <span>{{ totalCount }} results</span>
          <button
            type="button"
            class="btn btn-sm btn-secondary cursor-pointer"
            :disabled="currentPage <= 1 || loading"
            @click="runSearch(currentPage - 1)"
          >
            Previous
          </button>
          <button
            type="button"
            class="btn btn-sm btn-secondary cursor-pointer"
            :disabled="currentPage * 15 >= totalCount || loading"
            @click="runSearch(currentPage + 1)"
          >
            Next
          </button>
        </div>
        <div v-else></div>
        <button
          type="button"
          class="btn btn-secondary cursor-pointer ml-auto"
          @click="emit('close')"
        >
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>
