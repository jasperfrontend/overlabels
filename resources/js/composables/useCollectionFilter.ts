import { computed, ref, type ComputedRef, type Ref } from 'vue';

/**
 * Client-side narrowing of a collection already on the page. The other half of
 * CollectionFilter.vue: the component is the box, this is what it does.
 *
 * `matches` receives the query already lowercased and trimmed - the same
 * contract as GroupedCollection's `matches` prop, so a predicate written for
 * one works unchanged in the other.
 *
 * Nothing here is debounced. The rows are in memory and the lists are bounded
 * per user, so a keystroke costs one pass over an array; the debounce on the
 * URL-backed search bars exists to avoid a request per keystroke, which has no
 * equivalent here.
 */
export function useCollectionFilter<TItem>(
  items: () => TItem[],
  matches: (item: TItem, query: string) => boolean,
): {
  /** Bind to CollectionFilter's v-model. Raw, as typed. */
  query: Ref<string>;
  /** Lowercased and trimmed - what `matches` is handed. */
  normalized: ComputedRef<string>;
  /** True once the query is more than whitespace. */
  filtering: ComputedRef<boolean>;
  filtered: ComputedRef<TItem[]>;
} {
  const query = ref('');

  const normalized = computed(() => query.value.toLowerCase().trim());
  const filtering = computed(() => normalized.value !== '');

  const filtered = computed(() => (filtering.value ? items().filter((item) => matches(item, normalized.value)) : items()));

  return { query, normalized, filtering, filtered };
}
