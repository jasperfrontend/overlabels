<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <main
    data-slot="sidebar-inset"
    :class="cn(
      // min-w-0: as a flex item of the sidebar wrapper, main's automatic minimum
      // width is its min-content, and a single nowrap leaf deep inside a page (a
      // truncated overlay description) can push it wider than the viewport and
      // give the whole app a horizontal scrollbar. Allowing main to shrink is
      // what lets every min-w-0 / truncate below it actually work.
      'bg-background dark:bg-background/80 relative flex w-full min-w-0 flex-1 flex-col',
      'md:peer-data-[variant=inset]:m-2 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow-sm md:peer-data-[variant=inset]:peer-data-[state=collapsed]:ml-0',
      props.class,
    )"
  >
    <slot />
  </main>
</template>
