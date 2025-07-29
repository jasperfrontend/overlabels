<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { TabsRoot, type TabsRootProps, useForwardPropsEmits } from 'reka-ui'

interface Props extends TabsRootProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
const emits = defineEmits<{
  'update:modelValue': [value: string]
}>()

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <TabsRoot
    data-slot="tabs"
    :class="cn('w-full', props.class)"
    v-bind="forwarded"
  >
    <slot />
  </TabsRoot>
</template>