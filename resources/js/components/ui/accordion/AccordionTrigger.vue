<script setup lang="ts">
import { cn } from '@/lib/utils'
import { AccordionHeader, AccordionTrigger, type AccordionTriggerProps } from 'reka-ui'
import { ChevronDown } from 'lucide-vue-next'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<AccordionTriggerProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props
  return delegated
})
</script>

<template>
  <AccordionHeader class="flex" data-slot="accordion-header">
    <AccordionTrigger
      data-slot="accordion-trigger"
      v-bind="delegatedProps"
      :class="cn(
        'flex flex-1 items-center justify-between py-3 text-sm font-medium transition-all cursor-pointer [&[data-state=open]>svg]:rotate-180',
        props.class,
      )"
    >
      <slot />
      <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform duration-200" />
    </AccordionTrigger>
  </AccordionHeader>
</template>
