<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps({
  ttContentClass: {
    type: String,
    required: false,
  },

  align: {
    type: [ String, Object, Array, Number, Function, Boolean],
    required: false,
    default: 'center',
  },
  side: {
    type: String,
    required: false,
    default: 'top',
  }
});
</script>
<template>
  <Tooltip :delay-duration="0">
    <TooltipTrigger as-child>
      <slot name="trigger" />
    </TooltipTrigger>
    <TooltipContent
      class="text-md rounded px-2 py-1"
      :class="props.ttContentClass"
      :side="side"
      :align="props.align"
      :side-offset="5"
      :align-offset="0"
      :arrow-padding="0"
      :collision-boundary="undefined"
      :collision-padding="0"
      hide-when-detached
      position-strategy="absolute"
      sticky="partial"
      update-position-strategy="optimized"
      avoid-collisions
    >
      <slot name="content" />
    </TooltipContent>
  </Tooltip>
</template>
