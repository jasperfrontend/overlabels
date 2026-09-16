<script setup lang="ts">
// The mark that says "an official Overlabels product". One shape, used on
// every product surface: the listing, the product page, the setup banner,
// the wiring subject. Fill is currentColor, so a text-* class colours it.
// With a label, the label is both the accessible name and a tooltip above
// the mark. It carries its own TooltipProvider because the products pages
// render outside AppLayout, where the app's provider is mounted; nesting
// one under it elsewhere is harmless.
// Icon: tdesign:verified-filled, TDesign Icons (MIT),
// https://github.com/Tencent/tdesign-icons/blob/main/LICENSE
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

// The provider and the tooltip root render no element of their own, so the
// class a caller passes has to be put on the svg by hand.
defineOptions({ inheritAttrs: false });
defineProps<{ label?: string }>();
</script>

<template>
  <TooltipProvider v-if="label" :delay-duration="150">
    <Tooltip>
      <TooltipTrigger as-child>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" role="img" :aria-label="label" tabindex="0" class="outline-none" v-bind="$attrs">
          <path
            fill="currentColor"
            d="M15.616 3.268L12 .186L8.383 3.268l-4.737.378l-.378 4.737L.186 12l3.082 3.617l.378 4.737l4.737.378l3.616 3.082l3.617-3.082l4.737-.378l.378-4.737L23.813 12l-3.082-3.617l-.378-4.737zM11 16.414L6.585 12L8 10.586l3 3l5.5-5.5L17.914 9.5z"
          />
        </svg>
      </TooltipTrigger>
      <TooltipContent side="top" align="center">{{ label }}</TooltipContent>
    </Tooltip>
  </TooltipProvider>

  <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" role="presentation" aria-hidden="true" v-bind="$attrs">
    <path
      fill="currentColor"
      d="M15.616 3.268L12 .186L8.383 3.268l-4.737.378l-.378 4.737L.186 12l3.082 3.617l.378 4.737l4.737.378l3.616 3.082l3.617-3.082l4.737-.378l.378-4.737L23.813 12l-3.082-3.617l-.378-4.737zM11 16.414L6.585 12L8 10.586l3 3l5.5-5.5L17.914 9.5z"
    />
  </svg>
</template>
