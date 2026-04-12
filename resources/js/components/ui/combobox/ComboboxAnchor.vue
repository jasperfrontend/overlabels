<script setup lang="ts">
import type { ComboboxAnchorProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxAnchor, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxAnchorProps & { class?: HTMLAttributes['class'] }>()
const delegated = reactiveOmit(props, 'class')
const forwarded = useForwardProps(delegated)
</script>

<template>
  <ComboboxAnchor
    v-bind="forwarded"
    :class="cn('flex w-full items-center rounded-none border border-border bg-background transition-[border-color] focus-within:border-violet-400 dark:border-violet-300/30', props.class)"
  >
    <slot />
  </ComboboxAnchor>
</template>
