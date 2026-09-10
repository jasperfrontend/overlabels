<script setup lang="ts">
/**
 * The one filter box that sits above a collection: magnifier, input, and a
 * toolbar slot on its right.
 *
 * Lifted out of GroupedCollection, which had it inline and was the only thing
 * on the platform wearing it. Every settings page that lists things uses it
 * now, so the box looks and behaves the same on /settings/controls as it does
 * on /settings/tokens.
 *
 * This is a FILTER, not a search: it narrows rows already rendered on the
 * page, fetches nothing and does not touch the URL. A surface that has to
 * reach rows the page does not hold wants a server-side search instead - see
 * FilterSearchInput and useSearchFilters, which round-trip through the query
 * string.
 *
 * Pairs with useCollectionFilter for the narrowing itself.
 */
import { computed } from 'vue';
import { Search } from '@lucide/vue';

const props = withDefaults(
  defineProps<{
    /** What one item is called, for the default placeholder. */
    noun?: string;
    nounPlural?: string;
    placeholder?: string;
  }>(),
  { noun: 'item', nounPlural: undefined, placeholder: undefined },
);

const model = defineModel<string>({ required: true });

defineSlots<{
  /** Sits to the right of the input. */
  toolbar?(): unknown;
}>();

const inputPlaceholder = computed(() => props.placeholder ?? `Filter ${props.nounPlural ?? `${props.noun}s`}...`);
</script>

<template>
  <div class="flex items-center gap-3">
    <div class="relative flex-1">
      <Search :size="15" class="absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
      <!-- Placeholder doubles as the accessible name: the box carries no
           visible label, so without this a screen reader announces it unnamed. -->
      <input v-model="model" :placeholder="inputPlaceholder" :aria-label="inputPlaceholder" class="input-border w-full py-1.5 pr-2.5 pl-8 text-sm" />
    </div>
    <slot name="toolbar" />
  </div>
</template>
