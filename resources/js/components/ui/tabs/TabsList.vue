<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { TabsList as TabsListPrimitive, type TabsListProps } from 'reka-ui'

interface Props extends TabsListProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<Props>()
</script>

<template>
  <TabsListPrimitive
    data-slot="tabs-list"
    :class="cn(
      'inline-flex items-center justify-center rounded-sm bg-sidebar p-1 text-muted-foreground',
      props.class,
    )"
    v-bind="props"
  >
    <slot />
  </TabsListPrimitive>
</template>
