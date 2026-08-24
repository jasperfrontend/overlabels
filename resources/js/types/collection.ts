/** One group of a GroupedCollection: a labelled, collapsible bucket of items. */
export interface CollectionGroup<TItem> {
  /** Stable key for v-for and for the persisted expanded state. */
  key: string;
  /** Group header text. Also matched by the filter. */
  label: string;
  items: TItem[];
}
