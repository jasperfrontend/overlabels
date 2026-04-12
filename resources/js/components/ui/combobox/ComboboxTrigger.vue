<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { ComboboxTrigger } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <ComboboxTrigger
    :class="cn('flex cursor-pointer items-center px-2 text-muted-foreground transition hover:text-foreground', props.class)"
  >
    <slot />
  </ComboboxTrigger>
</template>
