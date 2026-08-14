<script setup lang="ts">
import { computed, ref } from 'vue';
import { ExternalLink, Search } from '@lucide/vue';
import ControlTypeCard from './ControlTypeCard.vue';
import ProviderIcon from '@/components/ProviderIcon.vue';
import { CONTROL_TYPES, PRESET_GROUPS } from './controlTypeCatalog';
import { getPresetsForSource, type ServicePreset } from './controlPresets';
import { fuzzyMatch, presetHaystack } from '@/utils/services';
import type { OverlayControl, OverlayTemplate } from '@/types';

/**
 * Step one of "Add a control": choose what you are adding.
 *
 * Two routes sit side by side rather than behind tabs, because they answer
 * different questions and hiding either one costs a discovery. On the left,
 * the eight kinds you build yourself, each with a demo of what it looks like
 * on screen. On the right, the ready-made controls that come wired up from
 * Twitch and whichever services are connected.
 *
 * Each column owns its own search, sitting directly above the thing it
 * filters. One shared search box read as if it drove the column it sat in
 * while actually reshaping the other one.
 */
const props = defineProps<{
  template: OverlayTemplate;
  connectedServices?: string[];
  existingControls?: OverlayControl[];
}>();

const emit = defineEmits<{
  (e: 'select-type', type: OverlayControl['type']): void;
  (e: 'select-preset', source: string, preset: ServicePreset): void;
}>();

const typeSearch = ref('');
const presetSearch = ref('');
const typeSearchInput = ref<HTMLInputElement | null>(null);

function focusSearch() {
  typeSearchInput.value?.focus();
}

defineExpose({ focusSearch });

const typeQuery = computed(() => typeSearch.value.trim());
const presetQuery = computed(() => presetSearch.value.trim());

/** Presets already on this template are not offered again. */
function isAlreadyAdded(source: string, key: string): boolean {
  return (props.existingControls ?? []).some((c) => c.source === source && c.key === key);
}

interface RenderedPresetGroup {
  source: string;
  label: string;
  blurb: string;
  presets: ServicePreset[];
}

/**
 * One pass over the group table, replacing what used to be sixteen near
 * identical computeds (a `show*` and an `available*` per service). Adding a
 * ninth service is now a row in PRESET_GROUPS and nothing else.
 */
function buildGroups(applySearch: boolean): RenderedPresetGroup[] {
  if (props.template?.type !== 'static') return [];
  const connected = props.connectedServices ?? [];

  return PRESET_GROUPS.filter((g) => g.requiresService === null || connected.includes(g.requiresService))
    .map((g) => ({
      source: g.source,
      label: g.label,
      blurb: g.blurb,
      presets: getPresetsForSource(g.source).filter(
        (p) => !isAlreadyAdded(g.source, p.key) && (!applySearch || fuzzyMatch(presetQuery.value, presetHaystack(g.source, p.label))),
      ),
    }))
    .filter((g) => g.presets.length > 0);
}

const presetGroups = computed(() => buildGroups(true));

// Whether the lane exists at all, independent of what is typed in its search.
// Without this the whole column would vanish mid-search and the layout would jump.
const hasPresetLane = computed(() => buildGroups(false).length > 0);

const visibleTypes = computed(() => {
  if (!typeQuery.value) return CONTROL_TYPES;
  return CONTROL_TYPES.filter((meta) => fuzzyMatch(typeQuery.value, `${meta.name} ${meta.tagline} ${meta.goodFor.join(' ')}`));
});
</script>

<template>
  <div class="grid gap-10" :class="hasPresetLane ? 'xl:grid-cols-3' : ''">
    <!-- Build your own -->
    <section :class="hasPresetLane ? 'xl:col-span-2' : ''">
      <header class="mb-4">
        <h3 class="text-lg font-semibold text-foreground">Build your own</h3>
        <p class="mt-1 text-sm text-foreground/75">
          Eight kinds of control. Pick the one that matches the thing you want to change while you are live.
        </p>
      </header>

      <div class="relative mb-4">
        <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
          ref="typeSearchInput"
          v-model="typeSearch"
          type="search"
          class="input-border w-full pl-9"
          placeholder="Search control types..."
          aria-label="Search control types"
        />
      </div>

      <div v-if="visibleTypes.length" class="grid gap-4 sm:grid-cols-2" :class="hasPresetLane ? '' : '2xl:grid-cols-3'">
        <ControlTypeCard v-for="meta in visibleTypes" :key="meta.type" :meta="meta" selectable @click="emit('select-type', meta.type)" />
      </div>
      <p v-else class="border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">
        No control type matches "{{ typeQuery }}".
      </p>
    </section>

    <!-- Ready-made, from connected services -->
    <section v-if="hasPresetLane" class="xl:col-span-1">
      <div class="xl:sticky xl:top-0">
        <header class="mb-4">
          <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-lg font-semibold text-foreground">Ready-made</h3>
            <a
              href="/help/integration-presets"
              target="_blank"
              rel="noopener"
              class="flex shrink-0 cursor-pointer items-center gap-1 text-xs text-violet-500 hover:underline dark:text-violet-400"
            >
              <ExternalLink class="size-3" />
              Reference
            </a>
          </div>
          <p class="mt-1 text-sm text-foreground/75">
            Already wired up. These fill themselves in from Twitch and the services you connected, so you only pick where they appear.
          </p>
          <p class="mt-2 border-l-2 border-violet-400/40 pl-3 text-xs text-foreground/70">
            Service controls are shared across all your overlays. Adding one here makes it available everywhere, you do not add it per overlay.
          </p>
        </header>

        <div class="relative mb-4">
          <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            v-model="presetSearch"
            type="search"
            class="input-border w-full pl-9"
            placeholder="Search ready-made controls..."
            aria-label="Search ready-made controls"
          />
        </div>

        <div class="space-y-5 xl:max-h-[52vh] xl:overflow-y-auto xl:pr-2">
          <p v-if="!presetGroups.length" class="text-sm text-muted-foreground">No ready-made control matches "{{ presetQuery }}".</p>

          <div v-for="group in presetGroups" :key="group.source" class="space-y-2">
            <div class="flex items-center gap-2">
              <ProviderIcon :source="group.source" class="size-3.5 shrink-0 text-violet-500 dark:text-violet-400" />
              <h4 class="text-sm font-semibold text-foreground">{{ group.label }}</h4>
              <span class="text-xs text-muted-foreground">{{ group.presets.length }}</span>
            </div>
            <p class="text-xs text-muted-foreground">{{ group.blurb }}</p>

            <div class="space-y-1.5">
              <button
                v-for="preset in group.presets"
                :key="preset.key"
                type="button"
                class="flex w-full cursor-pointer flex-col gap-0.5 border border-border/60 bg-background px-3 py-2 text-left transition duration-150 hover:border-violet-500/60 focus-visible:ring-2 focus-visible:ring-violet-400/40 focus-visible:outline-none dark:hover:border-violet-400/50"
                @click="emit('select-preset', group.source, preset)"
              >
                <span class="flex items-center justify-between gap-2">
                  <span class="truncate text-sm font-medium text-foreground">{{ preset.label }}</span>
                  <span class="shrink-0 border border-border/60 px-1.5 py-0.5 text-[10px] tracking-wide text-muted-foreground uppercase">
                    {{ preset.type }}
                  </span>
                </span>
                <code class="truncate font-mono text-[10px] text-muted-foreground"> [[[c:{{ group.source }}:{{ preset.key }}]]] </code>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
