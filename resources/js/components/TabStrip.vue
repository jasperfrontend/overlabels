<script setup lang="ts">
/**
 * The violet tab strip above a page's main panels. One component because the
 * same strip had been hand-copied three times (/templates/show, /templates/edit,
 * the dashboard) and had already drifted into two paddings, two active-text
 * colours and two ways of deciding which tab is on.
 *
 * It owns the STRIP ONLY, not the panels. Its callers render their panels in
 * their own bordered box below it, so anything that had to contain them - a
 * Reka Tabs root, say - would mean restructuring those pages instead of
 * swapping markup. Panels stay `v-if`/`v-show` on the bound key.
 */
import { ref, type Component, type ComponentPublicInstance } from 'vue';

export interface TabStripItem {
  key: string;
  label: string;
  icon: Component;
  /** Extra classes for a single tab, e.g. the product-mode green on Add to OBS. */
  class?: string;
}

const props = defineProps<{ tabs: TabStripItem[] }>();

const model = defineModel<string>({ required: true });

const buttons = ref<HTMLButtonElement[]>([]);

function setButton(el: Element | ComponentPublicInstance | null, index: number) {
  if (el) buttons.value[index] = el as HTMLButtonElement;
}

/**
 * Arrow-key navigation, paired with the roving tabindex below. Neither existed
 * while this was three copies of a plain button row.
 */
function onKeydown(event: KeyboardEvent, index: number) {
  const last = props.tabs.length - 1;

  let next: number | null = null;
  if (event.key === 'ArrowRight') next = index === last ? 0 : index + 1;
  else if (event.key === 'ArrowLeft') next = index === 0 ? last : index - 1;
  else if (event.key === 'Home') next = 0;
  else if (event.key === 'End') next = last;

  if (next === null) return;

  event.preventDefault();
  model.value = props.tabs[next].key;
  buttons.value[next]?.focus();
}
</script>

<template>
  <div class="flex flex-col justify-between gap-2 bg-violet-300/20 md:flex-row md:items-center dark:bg-violet-900/20">
    <div role="tablist" class="flex max-w-full touch-pan-x overflow-auto lg:touch-none">
      <button
        v-for="(tab, index) in tabs"
        :key="tab.key"
        :ref="(el) => setButton(el, index)"
        type="button"
        role="tab"
        :aria-selected="model === tab.key"
        :tabindex="model === tab.key ? 0 : -1"
        :class="[
          'flex cursor-pointer items-center gap-1.5 px-4 py-2.5 text-sm font-medium transition-colors hover:bg-background',
          model === tab.key
            ? 'border-t-2 border-t-violet-400 bg-white text-black dark:bg-violet-500/30 dark:text-violet-300 dark:hover:text-violet-200'
            : 'text-accent-foreground',
          tab.class ?? '',
        ]"
        @click="model = tab.key"
        @keydown="onKeydown($event, index)"
      >
        <component :is="tab.icon" class="h-4 w-4" />
        {{ tab.label }}
      </button>
    </div>

    <div v-if="$slots.actions" class="flex gap-2 px-2 pb-2 md:pb-0">
      <slot name="actions" />
    </div>
  </div>
</template>
