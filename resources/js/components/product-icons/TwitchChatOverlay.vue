<script setup lang="ts">
// Five chat lines, each ending at its own width.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <rect x="3" y="3" width="15" height="2" />
    <rect x="3" y="7.5" width="10" height="2" />
    <rect x="3" y="12" width="18" height="2" />
    <rect x="3" y="16.5" width="7" height="2" />
    <rect x="3" y="21" width="13" height="2" />
  </IconFrame>
</template>
