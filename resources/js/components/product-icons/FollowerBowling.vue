<script setup lang="ts">
// The rack seen from above: ten pins, 4-3-2-1.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <circle cx="5.4" cy="6" r="1.7" />
    <circle cx="9.8" cy="6" r="1.7" />
    <circle cx="14.2" cy="6" r="1.7" />
    <circle cx="18.6" cy="6" r="1.7" />
    <circle cx="7.6" cy="10" r="1.7" />
    <circle cx="12" cy="10" r="1.7" />
    <circle cx="16.4" cy="10" r="1.7" />
    <circle cx="9.8" cy="14" r="1.7" />
    <circle cx="14.2" cy="14" r="1.7" />
    <circle cx="12" cy="18" r="1.7" />
  </IconFrame>
</template>
