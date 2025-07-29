<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { TabsContent as TabsContentPrimitive, type TabsContentProps } from 'reka-ui'

interface Props extends TabsContentProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <TabsContentPrimitive
    data-slot="tabs-content"
    :class="cn(
      'mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
      props.class,
    )"
    v-bind="props"
  >
    <slot />
  </TabsContentPrimitive>
</template>