<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { ComboboxEmpty } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <ComboboxEmpty :class="cn('py-6 text-center text-sm text-muted-foreground', props.class)">
    <slot />
  </ComboboxEmpty>
</template>
