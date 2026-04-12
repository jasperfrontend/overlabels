<script setup lang="ts">
import type { ComboboxContentEmits, ComboboxContentProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import {
  ComboboxContent,
  ComboboxViewport,
  useForwardPropsEmits,
} from 'reka-ui'
import { cn } from '@/lib/utils'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<ComboboxContentProps & { class?: HTMLAttributes['class'] }>(),
  {
    align: 'start',
    sideOffset: 4,
  },
)
const emits = defineEmits<ComboboxContentEmits>()
const delegated = reactiveOmit(props, 'class')
const forwarded = useForwardPropsEmits(delegated, emits)
</script>

<template>
  <ComboboxContent
    v-bind="{ ...forwarded, ...$attrs }"
    :class="cn('z-50 w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-sm border border-sidebar bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0', props.class)"
  >
    <ComboboxViewport class="max-h-72 overflow-y-auto p-1">
      <slot />
    </ComboboxViewport>
  </ComboboxContent>
</template>
