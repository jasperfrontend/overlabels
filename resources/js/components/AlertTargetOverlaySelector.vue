<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Save, X } from '@lucide/vue';
import ActiveFirstPicker, { type PickerGroup } from '@/components/ActiveFirstPicker.vue';
import type { AlertsOverlayState } from '@/components/AlertsOverlayPanel.vue';

interface OverlayOption {
  id: number;
  name: string;
  slug: string;
}

// Where an alert fires. An empty target list is not "nowhere": it is the
// default rule (App\Services\Alerts\AlertsOverlay::targetSlugs()) - the
// account's Alerts overlay once OBS has loaded it, every static overlay
// before that. So "Only some overlays" with nothing picked is never saved:
// stored, it would quietly mean the default again.
const props = defineProps<{
  templateId: number;
  staticOverlays: OverlayOption[];
  initialIds: number[];
  alertsOverlay?: AlertsOverlayState | null;
}>();

const emit = defineEmits<{
  saved: [];
  error: [message: string];
}>();

const selectedIds = ref<number[]>([...props.initialIds]);
const mode = ref<'default' | 'only'>(props.initialIds.length > 0 ? 'only' : 'default');

const total = computed(() => props.staticOverlays.length);
const selected = computed(() => props.staticOverlays.filter((o) => selectedIds.value.includes(o.id)));
const isOnly = computed(() => mode.value === 'only');
const nothingPicked = computed(() => isOnly.value && selected.value.length === 0);

const defaultCard = computed(() => {
  const all = `${total.value} static overlay${total.value === 1 ? '' : 's'}`;
  if (props.alertsOverlay?.live) {
    return { title: 'Default: your Alerts overlay', body: 'Shows in your Alerts overlay, the one overlay in OBS that holds your alerts.' };
  }
  if (props.alertsOverlay?.overlay) {
    return { title: 'Default: all static overlays', body: `Shows on all ${all} until your Alerts overlay is in OBS.` };
  }
  return { title: 'Default: all static overlays', body: `Shows on all ${all}.` };
});

function letterOf(overlay: OverlayOption): string {
  const c = overlay.name.trim().charAt(0).toUpperCase();
  return c >= 'A' && c <= 'Z' ? c : '#';
}

const groups = computed<PickerGroup<OverlayOption>[]>(() => {
  const byLetter = new Map<string, OverlayOption[]>();
  for (const o of props.staticOverlays) {
    const l = letterOf(o);
    byLetter.set(l, [...(byLetter.get(l) ?? []), o]);
  }
  return [...byLetter.keys()].sort().map((l) => ({ key: l, title: l, section: 'Browse by first letter', items: byLetter.get(l) ?? [] }));
});

function toggle(overlay: OverlayOption) {
  selectedIds.value = selectedIds.value.includes(overlay.id)
    ? selectedIds.value.filter((id) => id !== overlay.id)
    : [...selectedIds.value, overlay.id];
  save();
}

function setMode(next: 'default' | 'only') {
  if (mode.value === next) return;
  mode.value = next;
  save();
}

const isSaving = ref(false);

/**
 * Every change saves on its own (`announce` false); the Save button does the
 * same save and says so. In default mode the picks stay on screen and come
 * back when you switch to "Only some" again, but the server gets none.
 */
function save(announce = false) {
  if (nothingPicked.value) return;
  isSaving.value = true;

  router.put(
    route('templates.target-overlays', props.templateId),
    { overlay_ids: isOnly.value ? selectedIds.value : [] },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        if (announce) emit('saved');
      },
      onError: () => emit('error', 'Failed to save targeting settings.'),
      onFinish: () => {
        isSaving.value = false;
      },
    },
  );
}

function modeClass(active: boolean): string {
  return active ? 'border-[#1DAAFF] bg-[#1DAAFF]/10' : 'border-sidebar-border bg-card hover:border-[#1DAAFF]';
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-end gap-3">
      <button type="button" :disabled="isSaving || nothingPicked" class="btn btn-primary btn-sm cursor-pointer" @click="save(true)">
        <Save class="h-3.5 w-3.5" />
        {{ isSaving ? 'Saving...' : 'Save targeting' }}
      </button>
    </div>

    <div role="radiogroup" aria-label="Where this alert fires" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <button
        type="button"
        role="radio"
        :aria-checked="!isOnly"
        class="flex cursor-pointer flex-col gap-1 border p-4 text-left transition-colors"
        :class="modeClass(!isOnly)"
        @click="setMode('default')"
      >
        <span class="text-sm font-semibold text-foreground">{{ defaultCard.title }}</span>
        <span class="text-xs text-muted-foreground">{{ defaultCard.body }}</span>
      </button>
      <button
        type="button"
        role="radio"
        :aria-checked="isOnly"
        :disabled="total === 0"
        class="flex cursor-pointer flex-col gap-1 border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-50"
        :class="modeClass(isOnly)"
        @click="setMode('only')"
      >
        <span class="text-sm font-semibold text-foreground">Only some overlays</span>
        <span class="text-xs text-muted-foreground">The alert fires only on the overlays you pick.</span>
      </button>
    </div>

    <div v-if="total === 0" class="border border-dashed border-violet-400/50 p-6 text-center text-sm text-muted-foreground">
      You have no static overlays yet.
    </div>

    <section v-else-if="!isOnly" class="border border-sidebar-border bg-card p-4 text-sm text-muted-foreground sm:p-5">
      This alert follows the default. Choose Only some overlays to pick where it fires.
      <template v-if="selected.length > 0">
        Your {{ selected.length }} pick{{ selected.length === 1 ? ' is' : 's are' }} kept if you switch back.
      </template>
    </section>

    <ActiveFirstPicker
      v-else
      :groups="groups"
      :item-key="(o: OverlayOption) => String(o.id)"
      :item-name="(o: OverlayOption) => o.name"
      :item-sub="(o: OverlayOption) => o.slug"
      :item-search-text="(o: OverlayOption) => `${o.name} ${o.slug}`"
      :is-picked="(o: OverlayOption) => selectedIds.includes(o.id)"
      add-label="Add overlay"
      :search-placeholder="`Type to search ${total} overlays by name or slug`"
      back-label="All letters"
      :group-hint="(g: PickerGroup<OverlayOption>) => `Overlays starting with ${g.title}, ${g.items.length} found`"
      :tile-columns="6"
      @toggle="toggle"
    >
      <template #title>{{ selected.length === 0 ? 'No overlays picked' : `Fires on ${selected.length} of ${total} overlays` }}</template>
      <template #subtitle>Anywhere else, this alert stays quiet.</template>
      <template #actions>
        <button v-if="selected.length > 0" type="button" class="btn btn-cancel btn-sm cursor-pointer" @click="((selectedIds = []), save())">
          Clear
        </button>
      </template>

      <div v-if="selected.length > 0" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div
          v-for="overlay in selected"
          :key="overlay.id"
          class="flex items-center gap-2.5 border border-[#1DAAFF] bg-[#1DAAFF]/10 py-2 pr-2.5 pl-3.5"
        >
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-semibold text-foreground">{{ overlay.name }}</div>
            <div class="truncate font-mono text-[10.5px] text-muted-foreground">{{ overlay.slug }}</div>
          </div>
          <button
            type="button"
            class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center border border-violet-400/50 text-muted-foreground transition-colors hover:border-red-500 hover:text-red-500"
            :aria-label="`Remove ${overlay.name}`"
            :title="`Remove ${overlay.name}`"
            @click="toggle(overlay)"
          >
            <X :size="14" />
          </button>
        </div>
      </div>
      <div
        v-else
        class="border border-dashed border-orange-600/60 p-5 text-center text-sm text-orange-700 dark:border-orange-400/50 dark:text-orange-300"
      >
        No overlay picked yet, so nothing is saved. Pick at least one, or switch back to the default.
      </div>
    </ActiveFirstPicker>
  </div>
</template>
