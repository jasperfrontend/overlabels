<script setup lang="ts">
import { computed } from 'vue';
import { providerIcon, iconCells } from '@/utils/providerIcons';

// Monochrome shape-only source icon. Identity is carried entirely by form, so
// it stays readable in sunlight and under color vision deficiency. Size and
// color come from the parent (width/height classes + currentColor).
const props = withDefaults(
  defineProps<{
    source: string;
    gap?: number;
  }>(),
  { gap: 0.12 },
);

const icon = computed(() => providerIcon(props.source));
const cells = computed(() => iconCells(icon.value.bits, props.gap));
</script>

<template>
  <svg
    viewBox="0 0 4 4"
    fill="currentColor"
    shape-rendering="geometricPrecision"
    role="img"
    :aria-label="icon.label"
  >
    <rect
      v-for="(cell, i) in cells"
      :key="i"
      :x="cell.x"
      :y="cell.y"
      :width="cell.size"
      :height="cell.size"
      rx="0.12"
    />
  </svg>
</template>
