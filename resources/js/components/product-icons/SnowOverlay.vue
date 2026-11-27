<script setup lang="ts">
// A six-armed snowflake, each arm with one pair of branches.
import IconFrame from './IconFrame.vue';
import type { ProductIconProps } from './types';

const props = defineProps<ProductIconProps>();
</script>

<template>
  <IconFrame v-bind="props">
    <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <g v-for="turn in [0, 60, 120, 180, 240, 300]" :key="turn" :transform="`rotate(${turn} 12 12)`">
        <path d="M12 12V3" />
        <path d="M12 6.5l-2.5-2M12 6.5l2.5-2" />
      </g>
    </g>
  </IconFrame>
</template>
