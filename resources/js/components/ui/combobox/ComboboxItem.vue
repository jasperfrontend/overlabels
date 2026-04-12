<script setup lang="ts">
import type { ComboboxItemEmits, ComboboxItemProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { ComboboxItem, useForwardPropsEmits } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<ComboboxItemProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<ComboboxItemEmits>()
const delegated = reactiveOmit(props, 'class')
const forwarded = useForwardPropsEmits(delegated, emits)
</script>

<template>
  <ComboboxItem
    v-bind="forwarded"
    :class="cn('relative flex cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none transition data-[highlighted]:bg-violet-400/15 data-[highlighted]:text-foreground data-[state=checked]:bg-violet-400/10 data-[disabled]:pointer-events-none data-[disabled]:opacity-50', props.class)"
  >
    <slot />
  </ComboboxItem>
</template>
