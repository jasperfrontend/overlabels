<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppPageProps, BuilderPlacement } from '@/types';
import { renderTemplateSource } from '@/utils/renderTemplate';
import { prefixCss } from '@/utils/prefixCss';
import { BUILDER_ROOT } from '@/utils/composeBuilderTemplate';

export type ResizeHandle = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

const props = defineProps<{
  placement: BuilderPlacement;
  sampleData: Record<string, unknown>;
  selected: boolean;
  sourceStale?: boolean;
  /** Canvas scale factor, so chrome can be sized in screen pixels. */
  scale: number;
  /** The overlay's own CSS and head, as last applied to the previews. */
  customCss?: string;
  customHead?: string;
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

const page = usePage<AppPageProps>();
const locale = computed(() => page.props.auth.user?.locale ?? 'en-US');

const OUTLINE_PX = 2;
const OUTLINE_SELECTED_PX = 3;
const HANDLE_PX = 13;
const EDGE_PX = 9;

// A 1x1 block on a 24x24 grid is about 25 screen px across, and fixed-size
// handles would eat all of it, leaving nothing to grab for a move. Capping each
// handle as a share of the block keeps a middle to drag from at any size.
const handleSize = (screenPx: number, cap: string) => `min(${px(screenPx)}, ${cap})`;

// Both corner caps are shares of the block, so the chips that tuck in beside
// them have to be inset by the same expression rather than a fixed number -
// otherwise a small block insets its label further than the block is wide.
const cornerInset = computed(() => handleSize(HANDLE_PX, '30%'));
const edgeInset = computed(() => handleSize(EDGE_PX, '22%'));

/**
 * The name chip and the stale badge sit just inside the top corner grips.
 * Flush to the corner they were sitting *under* the grab handles, which reads
 * as the handle being part of the label.
 */
const chipStyle = computed(() => ({
  top: edgeInset.value,
  maxWidth: `calc(100% - ${cornerInset.value} - ${cornerInset.value})`,
}));

const outline = computed(() =>
  props.selected ? `${px(OUTLINE_SELECTED_PX)} solid var(--color-violet-500)` : `${px(OUTLINE_PX)} solid var(--color-sidebar-border)`,
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

// Render through the same two-pass pipeline the live overlay uses, so a block
// previews the way it will actually ship. The previous approach built a literal
// regex per sample key, which could only ever match a bare [[[tag]]] - anything
// carrying a pipe or a `??` default fell through and was then deleted outright
// by a catch-all strip, along with every conditional and foreach marker.
// Absent values still collapse to '' here, but now `??` defaults get their turn
// first. Rendered in a sandboxed iframe for free style isolation.
//
// The document is a one-cell copy of what composeBuilderTemplate emits: same
// wrapper elements, same scopes, same order. That structure is what lets the
// overlay's own CSS land here with the specificity it will really have - a bare
// `.value` override only ties a block's `.value` because BOTH sides pick up an
// id, and `:root`/`body` rules are REPLACED by their scope rather than
// prefixed. Appending the overlay CSS raw would agree with the compiler on the
// easy cases and quietly disagree on those two.
const previewDoc = computed(() => {
  const cellId = `blk-${props.placement.instance_id}`;
  const html = renderTemplateSource(props.placement.snapshot.html, props.sampleData, locale.value, true);
  const blockCss = prefixCss(renderTemplateSource(props.placement.snapshot.css, props.sampleData, locale.value, false), `#${cellId}`);
  const overlayCss = prefixCss(renderTemplateSource(props.customCss, props.sampleData, locale.value, false), BUILDER_ROOT);

  // The iframe IS the cell, so 100%/100% wrappers stand in for the grid-area
  // rule the compiler writes: a definite box for `height: 100%` to resolve
  // against. Heads go after the styles because that is the order the live
  // overlay ends up in - injectHead runs after injectStyle.
  return `<!DOCTYPE html>
<html lang="en">
  <head>
    <style>html,body{margin:0;padding:0;height:100%;background:transparent;overflow:hidden;}
#builder-root{width:100%;height:100%;}
#${cellId}{width:100%;height:100%;position:relative;overflow:hidden;}</style>
    <style>${blockCss}</style>
    <style>${overlayCss}</style>
    ${props.placement.snapshot.head}
    ${props.customHead ?? ''}
  </head>
  <body><div id="builder-root"><div id="${cellId}" class="builder-cell">${html}</div></div></body>
</html>`;
});

const gridArea = computed(() => `${props.placement.y} / ${props.placement.x} / span ${props.placement.h} / span ${props.placement.w}`);
</script>

<template>
  <div
    :style="{ gridArea, outline, outlineOffset: `-${px(selected ? OUTLINE_SELECTED_PX : OUTLINE_PX)}` }"
    :class="[
      'group relative min-h-0 min-w-0 cursor-grab touch-none overflow-hidden transition-all select-none active:cursor-grabbing',
      selected ? 'z-10' : '',
    ]"
    @click.stop="emit('select', placement.instance_id)"
    @pointerdown="onPointerDown"
  >
    <iframe :srcdoc="previewDoc" class="pointer-events-none h-full w-full border-0" sandbox="" tabindex="-1" :title="placement.block_name" />
    <div
      class="pointer-events-none absolute truncate bg-sidebar-accent/90 font-mono text-foreground opacity-0 transition-opacity duration-300 group-hover:opacity-100"
      :style="{ ...chipStyle, left: cornerInset, fontSize: px(12), padding: `${px(1)} ${px(6)}` }"
    >
      {{ placement.block_name }}
    </div>
    <div
      v-if="sourceStale"
      class="pointer-events-none absolute truncate bg-amber-500/90 font-medium text-black"
      :style="{ ...chipStyle, right: cornerInset, fontSize: px(10), padding: `${px(1)} ${px(6)}` }"
      title="The source of this block changed since it was placed. Select it to refresh."
    >
      Source updated
    </div>

    <!-- Resize affordances. Hidden until the block is hovered or selected, so a
         canvas at rest stays readable, then deliberately chunky: at a 0.35
         scale anything subtle is invisible. -->
    <div class="pointer-events-none absolute inset-0 opacity-0 transition-opacity group-hover:opacity-100" :class="{ 'opacity-100': selected }">
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
