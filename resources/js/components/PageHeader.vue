<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import type { HTMLAttributes } from 'vue';

withDefaults(defineProps<{
  title: string;
  description?: string;
  titleClass?: HTMLAttributes['class'];
}>(), {
  description: undefined,
  titleClass: undefined,
});
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <Heading :title="title" :description="description" :title-class="titleClass" />
    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
