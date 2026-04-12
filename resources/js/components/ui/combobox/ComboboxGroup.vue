<script setup lang="ts">
import type { ComboboxGroupProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxGroup, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxGroupProps & { class?: HTMLAttributes['class'] }>()
const delegated = reactiveOmit(props, 'class')
const forwarded = useForwardProps(delegated)
</script>

<template>
  <ComboboxGroup v-bind="forwarded" :class="cn('text-foreground', props.class)">
    <slot />
  </ComboboxGroup>
</template>
