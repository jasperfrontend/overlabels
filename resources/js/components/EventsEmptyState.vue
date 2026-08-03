<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';

/**
 * Empty state for the three event feeds. The advice only ever names a filter
 * that is actually set, and the way out of a search is offered as a button
 * rather than described - the filter panel is collapsible on every feed, so
 * when this shows the search box is frequently not on screen at all.
 */
defineProps<{
  search: string;
  range: string;
}>();

defineEmits<{ 'clear-search': [] }>();
</script>

<template>
  <EmptyState>
    <template v-if="search">
      No events match <span class="font-medium text-foreground">"{{ search }}"</span>.
      <button
        type="button"
        class="cursor-pointer underline underline-offset-2 hover:text-foreground"
        @click="$emit('clear-search')"
      >
        Clear the search</button><template v-if="range !== 'all'"> or widen the time range</template>.
    </template>
    <template v-else-if="range !== 'all'">
      No events in this time range. Try widening it.
    </template>
    <template v-else>
      No events match your filters.
    </template>
  </EmptyState>
</template>
