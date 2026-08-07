<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { BuilderPlacement as BuilderPlacementType } from '@/types';
import BuilderPlacement, { type ResizeHandle } from '@/components/builder/BuilderPlacement.vue';

const props = defineProps<{
  grid: { cols: number; rows: number; gap: number };
  canvas: { width: number; height: number };
  placements: BuilderPlacementType[];
  selectedId: string | null;
  sampleData: Record<string, unknown>;
  isCellOccupied: (x: number, y: number) => boolean;
  stalePlacementIds?: Set<string>;
}>();

const emit = defineEmits<{
  cellClick: [x: number, y: number];
  select: [id: string | null];
  moveTo: [id: string, x: number, y: number];
  resizeTo: [id: string, x: number, y: number, w: number, h: number];
  syncAll: [];
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
  endResize();
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

// Drag-to-resize. Same shape as the move drag: the canvas turns the pointer
// into a grid cell, builds the rect the handle implies, and hands it to the
// parent. Only the dragged side moves; the opposite one stays pinned. The
// parent validates, so a resize can no more overlap or overflow than a move
// can - it just stops at the last rect that fit.
const resize = ref<{ id: string; handle: ResizeHandle } | null>(null);

function onResizeStart(id: string, handle: ResizeHandle, e: PointerEvent) {
  resize.value = { id, handle };
  window.addEventListener('pointermove', onResizeMove);
  window.addEventListener('pointerup', endResize);
  window.addEventListener('pointercancel', endResize);
  onResizeMove(e);
}

function onResizeMove(e: PointerEvent) {
  const r = resize.value;
  if (!r) return;
  const placement = props.placements.find((p) => p.instance_id === r.id);
  const cell = cellAt(e);
  if (!placement || !cell) return;

  let { x, y, w, h } = placement;

  if (r.handle.includes('w')) {
    // The right edge is pinned, so the left one can travel up to it.
    const left = Math.min(cell.x, x + w - 1);
    w += x - left;
    x = left;
  } else if (r.handle.includes('e')) {
    w = Math.max(cell.x - x + 1, 1);
  }

  if (r.handle.includes('n')) {
    const top = Math.min(cell.y, y + h - 1);
    h += y - top;
    y = top;
  } else if (r.handle.includes('s')) {
    h = Math.max(cell.y - y + 1, 1);
  }

  emit('resizeTo', r.id, x, y, w, h);
}

function endResize() {
  resize.value = null;
  window.removeEventListener('pointermove', onResizeMove);
  window.removeEventListener('pointerup', endResize);
  window.removeEventListener('pointercancel', endResize);
}

/**
 * Chrome is authored in screen pixels and divided back out, because the canvas
 * is drawn at 1920x1080 and scaled to fit. A 1px border lands somewhere near
 * 0.35px, which Firefox renders on some edges of a box and not others - that is
 * the whole reason cell outlines used to flicker in and out of existence.
 */
const screenPx = (n: number) => `${n / scale.value}px`;

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
    <div
      v-if="stalePlacementIds && stalePlacementIds.size > 0"
      class="mb-2 flex flex-wrap items-center justify-between gap-2 border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-foreground"
    >
      <span>
        The source of {{ stalePlacementIds.size }} placed block{{ stalePlacementIds.size === 1 ? '' : 's' }} changed. Sync into this session?
      </span>
      <button type="button" class="btn btn-cancel btn-sm shrink-0 cursor-pointer" @click="emit('syncAll')">Sync all</button>
    </div>
    <div :style="{ height: `${canvas.height * scale}px` }" class="overflow-hidden">
      <div
        ref="gridEl"
        class="grid border border-sidebar-border"
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
        <!-- Empty cells carry the checkerboard, placed blocks don't. That one
             swap is what tells occupied from free at a glance, even when a
             block renders almost nothing - which overlay blocks often do. -->
        <button
          v-for="cell in emptyCells"
          :key="`${cell.x}-${cell.y}`"
          type="button"
          :style="{
            gridArea: `${cell.y} / ${cell.x} / span 1 / span 1`,
            outline: `${screenPx(1)} dashed var(--color-sidebar-border)`,
            outlineOffset: `-${screenPx(1)}`,
            fontSize: screenPx(28),
          }"
          class="group cursor-pointer bg-[repeating-conic-gradient(var(--color-sidebar)_0%_25%,transparent_0%_50%)] bg-size-[32px_32px] transition-colors hover:bg-violet-400/15"
          :aria-label="`Place a block at column ${cell.x}, row ${cell.y}`"
          @click.stop="emit('cellClick', cell.x, cell.y)"
        >
          <span class="text-transparent select-none group-hover:text-violet-400">+</span>
        </button>

        <BuilderPlacement
          v-for="placement in placements"
          :key="placement.instance_id"
          :placement="placement"
          :sample-data="sampleData"
          :selected="placement.instance_id === selectedId"
          :source-stale="stalePlacementIds?.has(placement.instance_id) ?? false"
          :scale="scale"
          @select="(id) => emit('select', id)"
          @drag-start="onDragStart"
          @resize-start="onResizeStart"
        />
      </div>
    </div>
  </div>
</template>
