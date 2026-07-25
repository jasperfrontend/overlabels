<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { BuilderPlacement as BuilderPlacementType } from '@/types';
import BuilderPlacement from '@/components/builder/BuilderPlacement.vue';

const props = defineProps<{
  grid: { cols: number; rows: number; gap: number };
  canvas: { width: number; height: number };
  placements: BuilderPlacementType[];
  selectedId: string | null;
  sampleData: Record<string, string>;
  isCellOccupied: (x: number, y: number) => boolean;
}>();

const emit = defineEmits<{
  cellClick: [x: number, y: number];
  select: [id: string | null];
}>();

// The canvas renders at its real OBS size (1920x1080) and is scaled down with
// a transform to fit the available width, so block previews are pixel-true.
const wrapper = ref<HTMLDivElement | null>(null);
const scale = ref(0.5);
let observer: ResizeObserver | null = null;

onMounted(() => {
  observer = new ResizeObserver(() => {
    if (wrapper.value) {
      scale.value = Math.min(wrapper.value.clientWidth / props.canvas.width, 1);
    }
  });
  if (wrapper.value) observer.observe(wrapper.value);
});

onBeforeUnmount(() => observer?.disconnect());

const emptyCells = computed(() => {
  const cells: Array<{ x: number; y: number }> = [];
  for (let y = 1; y <= props.grid.rows; y++) {
    for (let x = 1; x <= props.grid.cols; x++) {
      if (!props.isCellOccupied(x, y)) cells.push({ x, y });
    }
  }
  return cells;
});
</script>

<template>
  <div ref="wrapper" class="w-full">
    <div :style="{ height: `${canvas.height * scale}px` }" class="overflow-hidden">
      <div
        class="grid border border-sidebar-border bg-[repeating-conic-gradient(theme(colors.sidebar.DEFAULT)_0%_25%,transparent_0%_50%)] bg-[length:32px_32px] dark:bg-[repeating-conic-gradient(rgba(255,255,255,0.03)_0%_25%,transparent_0%_50%)]"
        :style="{
          width: `${canvas.width}px`,
          height: `${canvas.height}px`,
          transform: `scale(${scale})`,
          transformOrigin: 'top left',
          gridTemplateColumns: `repeat(${grid.cols}, 1fr)`,
          gridTemplateRows: `repeat(${grid.rows}, 1fr)`,
          gap: `${grid.gap}px`,
        }"
        @click="emit('select', null)"
      >
        <button
          v-for="cell in emptyCells"
          :key="`${cell.x}-${cell.y}`"
          type="button"
          :style="{ gridArea: `${cell.y} / ${cell.x} / span 1 / span 1` }"
          class="group cursor-pointer border border-dashed border-sidebar-border/50 transition-colors hover:border-violet-400 hover:bg-violet-400/10"
          :aria-label="`Place a block at column ${cell.x}, row ${cell.y}`"
          @click.stop="emit('cellClick', cell.x, cell.y)"
        >
          <span class="text-4xl text-transparent select-none group-hover:text-violet-400">+</span>
        </button>

        <BuilderPlacement
          v-for="placement in placements"
          :key="placement.instance_id"
          :placement="placement"
          :sample-data="sampleData"
          :selected="placement.instance_id === selectedId"
          @select="(id) => emit('select', id)"
        />
      </div>
    </div>
  </div>
</template>
