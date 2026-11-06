<script setup lang="ts">
// The globe and one pin on it.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2" />
    <circle cx="14.5" cy="9.5" r="2.5" />
  </IconFrame>
</template>
