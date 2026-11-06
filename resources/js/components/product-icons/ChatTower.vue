<script setup lang="ts">
// Five blocks, each nudged off the one below, the top one hanging more than
// half off the side: a tower one block from toppling.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <rect x="4" y="18.8" width="11" height="3.2" />
    <rect x="5.5" y="14.7" width="11" height="3.2" />
    <rect x="4.5" y="10.6" width="11" height="3.2" />
    <rect x="6.5" y="6.5" width="11" height="3.2" />
    <rect x="12.5" y="2.4" width="11" height="3.2" />
  </IconFrame>
</template>
