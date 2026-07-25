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
  moveTo: [id: string, x: number, y: number];
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

onBeforeUnmount(() => {
  observer?.disconnect();
  endDrag();
});

// Drag-to-move. The placement captures the pointer; the canvas translates
// pointer position to grid cells and asks the parent to move the block. The
// parent validates against occupancy, so a drag can never overlap another
// block - the block just stops at the last valid cell.
const gridEl = ref<HTMLDivElement | null>(null);
const drag = ref<{ id: string; startX: number; startY: number; offsetX: number; offsetY: number; active: boolean } | null>(null);

function cellAt(e: PointerEvent): { x: number; y: number } | null {
  if (!gridEl.value) return null;
  const rect = gridEl.value.getBoundingClientRect();
  if (rect.width === 0) return null;
  // Work in unscaled canvas pixels; the live rect already reflects the scale.
  const ux = (e.clientX - rect.left) / (rect.width / props.canvas.width);
  const uy = (e.clientY - rect.top) / (rect.height / props.canvas.height);
  const periodX = (props.canvas.width - props.grid.gap * (props.grid.cols - 1)) / props.grid.cols + props.grid.gap;
  const periodY = (props.canvas.height - props.grid.gap * (props.grid.rows - 1)) / props.grid.rows + props.grid.gap;
  return {
    x: Math.min(Math.max(Math.floor(ux / periodX) + 1, 1), props.grid.cols),
    y: Math.min(Math.max(Math.floor(uy / periodY) + 1, 1), props.grid.rows),
  };
}

function onDragStart(id: string, e: PointerEvent) {
  const placement = props.placements.find((p) => p.instance_id === id);
  const cell = cellAt(e);
  if (!placement || !cell) return;
  emit('select', id);
  drag.value = {
    id,
    startX: e.clientX,
    startY: e.clientY,
    // Where inside the block it was grabbed, so the block doesn't jump
    // to put its top-left cell under the pointer.
    offsetX: Math.min(Math.max(cell.x - placement.x, 0), placement.w - 1),
    offsetY: Math.min(Math.max(cell.y - placement.y, 0), placement.h - 1),
    active: false,
  };
  window.addEventListener('pointermove', onDragMove);
  window.addEventListener('pointerup', endDrag);
  window.addEventListener('pointercancel', endDrag);
}

function onDragMove(e: PointerEvent) {
  const d = drag.value;
  if (!d) return;
  if (!d.active) {
    // A few px of slack keeps plain clicks as select, not micro-drags.
    if (Math.abs(e.clientX - d.startX) + Math.abs(e.clientY - d.startY) < 5) return;
    d.active = true;
  }
  const placement = props.placements.find((p) => p.instance_id === d.id);
  const cell = cellAt(e);
  if (!placement || !cell) return;
  const x = Math.min(Math.max(cell.x - d.offsetX, 1), props.grid.cols - placement.w + 1);
  const y = Math.min(Math.max(cell.y - d.offsetY, 1), props.grid.rows - placement.h + 1);
  if (x !== placement.x || y !== placement.y) emit('moveTo', d.id, x, y);
}

function endDrag() {
  drag.value = null;
  window.removeEventListener('pointermove', onDragMove);
  window.removeEventListener('pointerup', endDrag);
  window.removeEventListener('pointercancel', endDrag);
}

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
        ref="gridEl"
        class="grid border border-sidebar-border bg-[repeating-conic-gradient(var(--color-sidebar)_0%_25%,transparent_0%_50%)] bg-size-[32px_32px]"
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
          @drag-start="onDragStart"
        />
      </div>
    </div>
  </div>
</template>
