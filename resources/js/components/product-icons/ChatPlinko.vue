<script setup lang="ts">
// The board's pegs: one dot, then two, three, four and five. Upright, so it
// never reads as Follower Bowling's upside-down 4-3-2-1 rack.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <circle cx="12" cy="3.6" r="1.6" />
    <circle cx="9.8" cy="7.8" r="1.6" />
    <circle cx="14.2" cy="7.8" r="1.6" />
    <circle cx="7.6" cy="12" r="1.6" />
    <circle cx="12" cy="12" r="1.6" />
    <circle cx="16.4" cy="12" r="1.6" />
    <circle cx="5.4" cy="16.2" r="1.6" />
    <circle cx="9.8" cy="16.2" r="1.6" />
    <circle cx="14.2" cy="16.2" r="1.6" />
    <circle cx="18.6" cy="16.2" r="1.6" />
    <circle cx="3.2" cy="20.4" r="1.6" />
    <circle cx="7.6" cy="20.4" r="1.6" />
    <circle cx="12" cy="20.4" r="1.6" />
    <circle cx="16.4" cy="20.4" r="1.6" />
    <circle cx="20.8" cy="20.4" r="1.6" />
  </IconFrame>
</template>
