<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { ComboboxLabel } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <ComboboxLabel :class="cn('px-2 py-1.5 text-xs font-semibold text-muted-foreground', props.class)">
    <slot />
  </ComboboxLabel>
</template>
