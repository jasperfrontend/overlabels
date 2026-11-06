<script setup lang="ts">
/**
 * The square every product icon is drawn in. Owns the props, so each icon
 * file is nothing but its shapes. Shapes draw in currentColor; `color` sets
 * that on the <svg>, so a ring's stroke and a block's fill follow it alike.
 */
import { computed } from 'vue';
import type { ProductIconProps } from './types';

const props = withDefaults(defineProps<ProductIconProps>(), {
  size: '1em',
  padding: 0,
});

// Padding grows the canvas around the 24-unit grid rather than shrinking the
// mark, so the shapes keep their coordinates.
const extent = computed(() => 24 + 2 * props.padding);
const viewBox = computed(() => `${-props.padding} ${-props.padding} ${extent.value} ${extent.value}`);
const dimension = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
</script>

<template>
  <!-- width/height are presentation attributes, so a Tailwind size class on
       the icon still wins over the default. -->
  <svg
    :viewBox="viewBox"
    :width="dimension"
    :height="dimension"
    fill="currentColor"
    :style="props.color ? { color: props.color } : undefined"
    :role="props.title ? 'img' : undefined"
    :aria-label="props.title"
    :aria-hidden="props.title ? undefined : 'true'"
  >
    <title v-if="props.title">{{ props.title }}</title>
    <rect v-if="props.background" :x="-props.padding" :y="-props.padding" :width="extent" :height="extent" :fill="props.background" />
    <slot />
  </svg>
</template>
