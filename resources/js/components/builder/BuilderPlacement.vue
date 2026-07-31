<script setup lang="ts">
import { computed } from 'vue';
import type { BuilderPlacement } from '@/types';

export type ResizeHandle = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

const props = defineProps<{
  placement: BuilderPlacement;
  sampleData: Record<string, string>;
  selected: boolean;
  sourceStale?: boolean;
  /** Canvas scale factor, so chrome can be sized in screen pixels. */
  scale: number;
}>();

const emit = defineEmits<{
  select: [id: string];
  dragStart: [id: string, event: PointerEvent];
  resizeStart: [id: string, handle: ResizeHandle, event: PointerEvent];
}>();

// The canvas is drawn at 1920x1080 and scaled down to fit, so anything meant to
// be read by a human has to be authored in screen pixels and divided back out.
// A 1px border becomes 0.35px at a typical scale, which Firefox renders on some
// edges and not others - the borders here are specified thick enough that they
// survive the transform.
const px = (screenPx: number) => `${screenPx / props.scale}px`;

const OUTLINE_PX = 2;
const OUTLINE_SELECTED_PX = 3;
const HANDLE_PX = 13;
const EDGE_PX = 9;

// A 1x1 block on a 24x24 grid is about 25 screen px across, and fixed-size
// handles would eat all of it, leaving nothing to grab for a move. Capping each
// handle as a share of the block keeps a middle to drag from at any size.
const handleSize = (screenPx: number, cap: string) => `min(${px(screenPx)}, ${cap})`;

const outline = computed(() =>
  props.selected
    ? `${px(OUTLINE_SELECTED_PX)} solid var(--color-violet-500)`
    : `${px(OUTLINE_PX)} solid var(--color-sidebar-border)`,
);

/** Corner handles sit on top of the edge strips, so they win the shared pixels. */
const corners: Array<{ handle: ResizeHandle; cursor: string; style: Record<string, string> }> = [
  { handle: 'nw', cursor: 'nwse-resize', style: { top: '0', left: '0' } },
  { handle: 'ne', cursor: 'nesw-resize', style: { top: '0', right: '0' } },
  { handle: 'sw', cursor: 'nesw-resize', style: { bottom: '0', left: '0' } },
  { handle: 'se', cursor: 'nwse-resize', style: { bottom: '0', right: '0' } },
];

const cornerStyle = (style: Record<string, string>) => ({
  ...style,
  width: handleSize(HANDLE_PX, '30%'),
  height: handleSize(HANDLE_PX, '30%'),
  borderWidth: px(1.5),
});

/** Full-length strips: "move towards the edge" should be enough to grab one. */
const edges = computed(() => {
  const thickness = handleSize(EDGE_PX, '22%');

  return [
    { handle: 'n' as ResizeHandle, cursor: 'ns-resize', style: { top: '0', left: '0', right: '0', height: thickness } },
    { handle: 's' as ResizeHandle, cursor: 'ns-resize', style: { bottom: '0', left: '0', right: '0', height: thickness } },
    { handle: 'w' as ResizeHandle, cursor: 'ew-resize', style: { left: '0', top: '0', bottom: '0', width: thickness } },
    { handle: 'e' as ResizeHandle, cursor: 'ew-resize', style: { right: '0', top: '0', bottom: '0', width: thickness } },
  ];
});

// Pointer capture keeps move/up events flowing to this element even when the
// pointer crosses the preview iframes (which would otherwise swallow them).
// The canvas owns the actual drag math; this just hands it the pointer.
function onPointerDown(e: PointerEvent) {
  if (e.button !== 0) return;
  (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  emit('dragStart', props.placement.instance_id, e);
}

function onHandlePointerDown(handle: ResizeHandle, e: PointerEvent) {
  if (e.button !== 0) return;
  // Beat the move-drag on the wrapper to the punch: near an edge, resizing is
  // what the user meant.
  e.stopPropagation();
  (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  emit('select', props.placement.instance_id);
  emit('resizeStart', props.placement.instance_id, handle, e);
}

// Same preview approach as the template create page: substitute sample data
// into the block's code, strip leftover tag/conditional markers for visual
// cleanliness, render in a sandboxed iframe (free style isolation).
const previewDoc = computed(() => {
  let html = props.placement.snapshot.html;
  let css = props.placement.snapshot.css;

  Object.entries(props.sampleData).forEach(([tag, value]) => {
    const pattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    html = html.replace(pattern, value);
    css = css.replace(pattern, value);
  });
  html = html.replace(/\[\[\[[^\]]*]]]/g, '');
  css = css.replace(/\[\[\[[^\]]*]]]/g, '');

  // height:100% on html/body mirrors the compiled environment: there the
  // block's wrapper is a grid item with a definite height, so a block using
  // height:100% must resolve the same way inside the preview iframe.
  return `<!DOCTYPE html>
<html lang="en">
  <head><style>html,body{margin:0;padding:0;height:100%;background:transparent;overflow:hidden;}</style><style>${css}</style>${props.placement.snapshot.head}</head>
  <body>${html}</body>
</html>`;
});

const gridArea = computed(
  () => `${props.placement.y} / ${props.placement.x} / span ${props.placement.h} / span ${props.placement.w}`,
);
</script>

<template>
  <div
    :style="{ gridArea, outline, outlineOffset: `-${px(selected ? OUTLINE_SELECTED_PX : OUTLINE_PX)}` }"
    :class="[
      'group relative min-h-0 min-w-0 cursor-grab touch-none overflow-hidden select-none active:cursor-grabbing',
      selected ? 'z-10' : '',
    ]"
    @click.stop="emit('select', placement.instance_id)"
    @pointerdown="onPointerDown"
  >
    <iframe
      :srcdoc="previewDoc"
      class="pointer-events-none h-full w-full border-0"
      sandbox=""
      tabindex="-1"
      :title="placement.block_name"
    />
    <div
      class="pointer-events-none absolute top-0 left-0 max-w-full truncate bg-sidebar-accent/90 font-mono text-foreground"
      :style="{ fontSize: px(12), padding: `${px(1)} ${px(6)}` }"
    >
      {{ placement.block_name }}
    </div>
    <div
      v-if="sourceStale"
      class="pointer-events-none absolute top-0 right-0 bg-amber-500/90 font-medium text-black"
      :style="{ fontSize: px(10), padding: `${px(1)} ${px(6)}` }"
      title="The source of this block changed since it was placed. Select it to refresh."
    >
      Source updated
    </div>

    <!-- Resize affordances. Hidden until the block is hovered or selected, so a
         canvas at rest stays readable, then deliberately chunky: at a 0.35
         scale anything subtle is invisible. -->
    <div
      class="pointer-events-none absolute inset-0 opacity-0 transition-opacity group-hover:opacity-100"
      :class="{ 'opacity-100': selected }"
    >
      <div
        v-for="edge in edges"
        :key="edge.handle"
        class="pointer-events-auto absolute bg-violet-500/30 hover:bg-violet-500/60"
        :style="{ ...edge.style, cursor: edge.cursor }"
        aria-hidden="true"
        @pointerdown="onHandlePointerDown(edge.handle, $event)"
      />
      <div
        v-for="corner in corners"
        :key="corner.handle"
        class="pointer-events-auto absolute border-white bg-violet-500 hover:bg-violet-400"
        :style="{ ...cornerStyle(corner.style), cursor: corner.cursor }"
        aria-hidden="true"
        @pointerdown="onHandlePointerDown(corner.handle, $event)"
      />
    </div>
  </div>
</template>
