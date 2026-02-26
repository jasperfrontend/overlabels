<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes['class'];
    title: string;
    description?: string;
    titleClass?: HTMLAttributes['class'];
    descriptionClass?: HTMLAttributes['class'];
  }>(),
  {
    description: undefined,
  },
);
</script>

<template>
  <div :class="cn('space-y-0.5', props.class)">
    <h2 :class="cn('text-xl font-semibold tracking-tight', props.titleClass)">{{ props.title }}</h2>
    <p v-if="props.description" :class="cn('text-sm text-muted-foreground', props.descriptionClass)">{{ props.description }}</p>
  </div>
</template>
