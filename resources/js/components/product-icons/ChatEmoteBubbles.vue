<script setup lang="ts">
// Three bubbles of different sizes, floating apart.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <g fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="7.5" cy="15.5" r="4.5" />
      <circle cx="16.5" cy="7" r="3" />
      <circle cx="18" cy="18.5" r="2" />
    </g>
  </IconFrame>
</template>
