<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground border border-sidebar bg-sidebar/50 flex flex-col gap-0 rounded py-4',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
