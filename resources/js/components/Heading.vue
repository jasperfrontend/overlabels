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
    <!--
      The title row only becomes a flex container when something is actually
      slotted in. With neither slot filled it is a bare <div> around the <h2>,
      which renders identically to what every existing caller had - and there
      are 20-odd of them.
    -->
    <div :class="($slots.icon || $slots.afterTitle) && 'flex flex-wrap items-center gap-2'">
      <slot name="icon" />
      <h2 :class="cn('text-xl font-semibold tracking-tight', props.titleClass)">{{ props.title }}</h2>
      <slot name="afterTitle" />
    </div>
    <p v-if="props.description" :class="cn('text-sm text-foreground', props.descriptionClass)">{{ props.description }}</p>
  </div>
</template>
