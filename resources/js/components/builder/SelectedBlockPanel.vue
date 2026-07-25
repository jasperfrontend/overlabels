<script setup lang="ts">
import type { BuilderPlacement } from '@/types';
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUp, RefreshCcw, Trash2 } from '@lucide/vue';

defineProps<{
  placement: BuilderPlacement;
  sourceStale?: boolean;
}>();

const emit = defineEmits<{
  move: [dx: number, dy: number];
  resize: [dw: number, dh: number];
  remove: [];
  refreshSource: [];
}>();
</script>

<template>
  <div class="space-y-3 border border-sidebar-border bg-sidebar-accent p-4">
    <div class="text-sm font-medium text-accent-foreground">{{ placement.block_name }}</div>
    <div class="font-mono text-xs text-muted-foreground">
      {{ placement.w }} x {{ placement.h }} at column {{ placement.x }}, row {{ placement.y }}
    </div>

    <div v-if="sourceStale" class="space-y-2 border border-amber-500/40 bg-amber-500/10 p-2.5 text-xs text-foreground">
      <p>The source of this block changed since it was placed here.</p>
      <button type="button" class="btn btn-cancel btn-sm cursor-pointer" @click="emit('refreshSource')">
        <RefreshCcw class="mr-1.5 size-3.5" />
        Refresh from source
      </button>
    </div>

    <div class="flex items-center gap-2">
      <span class="w-12 text-xs text-muted-foreground">Move</span>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Move left" @click="emit('move', -1, 0)"><ArrowLeft class="size-4" /></button>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Move right" @click="emit('move', 1, 0)"><ArrowRight class="size-4" /></button>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Move up" @click="emit('move', 0, -1)"><ArrowUp class="size-4" /></button>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Move down" @click="emit('move', 0, 1)"><ArrowDown class="size-4" /></button>
    </div>

    <div class="flex items-center gap-2">
      <span class="w-12 text-xs text-muted-foreground">Width</span>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Narrower" @click="emit('resize', -1, 0)">-</button>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Wider" @click="emit('resize', 1, 0)">+</button>
      <span class="w-12 text-xs text-muted-foreground">Height</span>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Shorter" @click="emit('resize', 0, -1)">-</button>
      <button type="button" class="btn btn-cancel cursor-pointer px-2 py-1" aria-label="Taller" @click="emit('resize', 0, 1)">+</button>
    </div>

    <button type="button" class="btn btn-cancel cursor-pointer text-red-500" @click="emit('remove')">
      <Trash2 class="mr-1.5 size-4" />
      Remove block
    </button>
  </div>
</template>
