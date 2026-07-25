<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import type { BuilderMetadata } from '@/types';
import BuilderCanvas from '@/components/builder/BuilderCanvas.vue';
import BuilderGridControls from '@/components/builder/BuilderGridControls.vue';
import BlockPickerModal, { type LibraryBlock } from '@/components/builder/BlockPickerModal.vue';
import SelectedBlockPanel from '@/components/builder/SelectedBlockPanel.vue';
import { useBuilderState, type BuilderControlDef } from '@/composables/useBuilderState';
import { composeBuilderTemplate } from '@/utils/composeBuilderTemplate';

// The Builder editing surface inside the template edit page's Code tab.
// The page owns saving: it calls compose()/serialize()/controlsForImport()
// via the exposed API when its Save button submits.
const props = defineProps<{
  initial: BuilderMetadata;
  sampleData: Record<string, string>;
  blocks: LibraryBlock[];
}>();

const emit = defineEmits<{ dirty: []; error: [message: string] }>();

const state = useBuilderState(props.initial);

const pickerOpen = ref(false);
const pickerCell = ref<{ x: number; y: number } | null>(null);

function onCellClick(x: number, y: number) {
  pickerCell.value = { x, y };
  pickerOpen.value = true;
}

async function onPickBlock(block: LibraryBlock) {
  pickerOpen.value = false;
  const cell = pickerCell.value;
  if (!cell) return;

  try {
    const { data } = await axios.get(route('templates.blocks.snapshot', block.id));
    const span = data.default_span ?? { w: 4, h: 2 };
    const placed = state.addPlacement(
      { id: data.id, slug: data.slug, name: data.name },
      { head: data.head, html: data.html, css: data.css },
      (data.controls ?? []) as BuilderControlDef[],
      cell.x,
      cell.y,
      span.w,
      span.h,
    );
    if (!placed) {
      emit('error', 'That block does not fit there. Try an emptier spot or a bigger grid.');
      return;
    }
    emit('dirty');
  } catch {
    emit('error', 'Could not load that block. It may have been removed.');
  }
}

function markDirty<A extends unknown[]>(fn: (...args: A) => void) {
  return (...args: A) => {
    fn(...args);
    emit('dirty');
  };
}

const move = markDirty((dx: number, dy: number) => state.move(state.selectedId.value!, dx, dy));
const resize = markDirty((dw: number, dh: number) => state.resize(state.selectedId.value!, dw, dh));
const removeSelected = markDirty(() => state.remove(state.selectedId.value!));
const setGrid = markDirty(state.setGrid);

function onKeydown(e: KeyboardEvent) {
  if (!state.selectedId.value || pickerOpen.value) return;
  const target = e.target as HTMLElement | null;
  if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;

  const actions: Record<string, () => void> = {
    ArrowLeft: () => (e.shiftKey ? resize(-1, 0) : move(-1, 0)),
    ArrowRight: () => (e.shiftKey ? resize(1, 0) : move(1, 0)),
    ArrowUp: () => (e.shiftKey ? resize(0, -1) : move(0, -1)),
    ArrowDown: () => (e.shiftKey ? resize(0, 1) : move(0, 1)),
    Delete: () => removeSelected(),
    Backspace: () => removeSelected(),
  };
  if (actions[e.key]) {
    e.preventDefault();
    actions[e.key]();
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

defineExpose({
  compose: () => composeBuilderTemplate(state.serialize()),
  serialize: () => state.serialize(),
  controlsForImport: () => state.controlsForImport(),
  hasPlacements: () => state.placements.value.length > 0,
});
</script>

<template>
  <div class="grid grid-cols-1 gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_280px]">
    <div class="min-w-0 space-y-4">
      <div class="border border-sidebar-border bg-sidebar-accent p-4">
        <BuilderGridControls :grid="state.grid.value" @update="setGrid" />
      </div>

      <BuilderCanvas
        :grid="state.grid.value"
        :canvas="state.canvas.value"
        :placements="state.placements.value"
        :selected-id="state.selectedId.value"
        :sample-data="sampleData"
        :is-cell-occupied="(x, y) => state.occupied(x, y)"
        @cell-click="onCellClick"
        @select="(id) => (state.selectedId.value = id)"
      />

      <p class="text-sm text-muted-foreground">
        Click an empty cell to place a block. Click a block to select it, then use the panel or arrow keys to move it,
        Shift + arrows to resize, Delete to remove. Save with the page's Save button.
      </p>
    </div>

    <div class="space-y-4">
      <SelectedBlockPanel
        v-if="state.selected.value"
        :placement="state.selected.value"
        @move="move"
        @resize="resize"
        @remove="removeSelected"
      />
      <div class="border border-sidebar-border bg-sidebar-accent p-4 text-xs text-muted-foreground">
        Blocks that use controls bring them along on save. Blocks sharing a control key stay in sync.
      </div>
    </div>

    <BlockPickerModal :open="pickerOpen" :blocks="blocks" @update:open="pickerOpen = $event" @pick="onPickBlock" />
  </div>
</template>
