<script setup lang="ts" generic="TItem">
/**
 * The one list. Every surface that renders a collection of rows uses this, so
 * a row on /templates, /triggers, /updates and /dashboard/lists looks and
 * behaves identically. Row chrome (left accent bar, hover, active, spacing,
 * hover-revealed actions) lives here and nowhere else.
 *
 * Rows navigate via a stretched link: an absolutely positioned <Link> covering
 * the whole row. That gives real anchor semantics - keyboard focus, middle
 * click, ctrl-click, "open in new tab", "copy link address" - which neither of
 * the two patterns this replaces had. It also means an action button only has
 * to sit above it (`relative z-10`, which the actions slot wrapper already
 * does) rather than stop click propagation.
 *
 * Interactive content inside the `item` slot needs its own `relative z-10`,
 * since the stretched link paints over everything that isn't positioned.
 */
import { Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';

const props = defineProps<{
  items: TItem[];
  /** Stable v-for key. */
  itemKey: (item: TItem) => string | number;
  /**
   * Row destination. Return null for a row that isn't navigable - it renders
   * without a stretched link and stays inert.
   */
  href?: (item: TItem) => string | null;
  /**
   * Accessible name for the stretched link. Without it a screen reader
   * announces an empty link, since the anchor has no text of its own.
   */
  label?: (item: TItem) => string;
  /** Extra classes on the row, e.g. a per-item hover border colour. */
  rowClass?: (item: TItem) => string | undefined;
  /** Shown by the default empty state. Override with the `empty` slot. */
  emptyMessage?: string;
  /** Dashed border on the default empty state. */
  emptyDashed?: boolean;
}>();

defineSlots<{
  /** Row content. Fills the row; actions are laid out beside it. */
  item(props: { item: TItem }): unknown;
  /** Buttons on the right. Hidden until hover/focus on pointer devices. */
  actions?(props: { item: TItem }): unknown;
  /** Replaces the default empty state. */
  empty?(): unknown;
}>();

function rowHref(item: TItem): string | null {
  return props.href?.(item) ?? null;
}
</script>

<template>
  <!-- Single root, so a caller can pass spacing classes through. -->
  <div class="flex flex-col gap-2">
    <div
      v-for="item in items"
      :key="itemKey(item)"
      class="collection-row group relative flex items-start justify-between gap-4 p-3"
      :class="rowClass?.(item)"
    >
      <Link
        v-if="rowHref(item)"
        :href="rowHref(item)!"
        :aria-label="label?.(item)"
        class="absolute inset-0 cursor-pointer"
      />

      <div class="min-w-0 flex-1">
        <slot name="item" :item="item" />
      </div>

      <!--
        Always visible below md: a touch device has no hover, so hiding the
        actions there makes them unreachable.
      -->
      <div
        v-if="$slots.actions"
        class="relative z-10 flex shrink-0 items-center gap-1 transition-opacity md:opacity-0 md:group-hover:opacity-100 md:focus-within:opacity-100"
      >
        <slot name="actions" :item="item" />
      </div>
    </div>

    <slot v-if="!items.length" name="empty">
      <EmptyState :message="emptyMessage" :dashed="emptyDashed" />
    </slot>
  </div>
</template>
