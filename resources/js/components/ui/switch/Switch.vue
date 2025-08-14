<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<SwitchRootProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<SwitchRootEmits>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <SwitchRoot
    data-slot="switch"
    v-bind="forwarded"
    :class="
      cn(
        'peer data-[state=checked]:bg-primary data-[state=unchecked]:bg-input focus-visible:ring-ring/50 focus-visible:border-ring aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )
    "
  >
    <SwitchThumb
      data-slot="switch-thumb"
      :class="
        cn(
          'pointer-events-none block size-5 rounded-full bg-background shadow-lg ring-0 transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0'
        )
      "
    />
  </SwitchRoot>
</template>