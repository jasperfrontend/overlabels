<script setup lang="ts">
import type { ComboboxInputProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxInput, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxInputProps & { class?: HTMLAttributes['class'] }>()
const delegated = reactiveOmit(props, 'class')
const forwarded = useForwardProps(delegated)
</script>

<template>
  <ComboboxInput
    v-bind="forwarded"
    :class="cn('flex-1 bg-transparent px-2 py-2 text-sm text-foreground outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50', props.class)"
  />
</template>
