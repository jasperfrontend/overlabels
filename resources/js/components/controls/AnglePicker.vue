<script setup lang="ts">
import { computed, ref } from 'vue';
import { angleFromPointer, gradientCss, normaliseAngle } from '@/utils/angleKnob';

/**
 * A gradient's angle as a line you drag, over a circle painted with the two
 * colors it turns. The filled handle is the FIRST color: drag it to where
 * that color should sit and the circle shows the exact gradient the overlay
 * paints (`gradientCss()`, the overlay's own 180deg offset).
 *
 * Mounted by ControlKnob for a number control that declares
 * `config.angle_colors`. `input` fires while dragging, `commit` once on
 * release, the same pair every knob emits. Arrow keys step 1, Shift steps 15;
 * Shift while dragging snaps to 15.
 */
const props = defineProps<{
  id: string;
  label: string;
  value: number;
  first: string;
  second: string;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  (e: 'input', value: number): void;
  (e: 'commit', value: number): void;
}>();

const angle = computed(() => normaliseAngle(props.value));
const fill = computed(() => gradientCss(angle.value, props.first, props.second));

const svg = ref<SVGSVGElement | null>(null);
const dragging = ref(false);
let last = 0;

function fromEvent(event: PointerEvent): number {
  const rect = svg.value!.getBoundingClientRect();

  return angleFromPointer(event.clientX - (rect.left + rect.width / 2), event.clientY - (rect.top + rect.height / 2), event.shiftKey ? 15 : 1);
}

function down(event: PointerEvent): void {
  if (props.disabled || event.button !== 0) return;
  dragging.value = true;
  // Capture keeps the drag alive outside the circle; a browser that refuses
  // it (no active pointer) still gets a working click.
  try {
    svg.value?.setPointerCapture(event.pointerId);
  } catch {
    // Nothing to do.
  }
  last = fromEvent(event);
  emit('input', last);
}

function move(event: PointerEvent): void {
  if (!dragging.value) return;
  const next = fromEvent(event);
  if (next === last) return;
  last = next;
  emit('input', last);
}

function up(): void {
  if (!dragging.value) return;
  dragging.value = false;
  emit('commit', last);
}

function key(event: KeyboardEvent): void {
  if (props.disabled) return;
  const step = event.shiftKey ? 15 : 1;
  const moves: Record<string, number> = { ArrowRight: step, ArrowUp: step, ArrowLeft: -step, ArrowDown: -step };

  let next: number | null = null;
  if (event.key in moves) next = normaliseAngle(angle.value + moves[event.key]);
  if (event.key === 'Home') next = 0;
  if (next === null) return;

  event.preventDefault();
  emit('input', next);
  emit('commit', next);
}
</script>

<template>
  <div class="relative size-28 shrink-0 select-none">
    <div class="absolute inset-[14%] rounded-full border-2 border-foreground/80" :style="{ background: fill }" />
    <svg
      :id="id"
      ref="svg"
      viewBox="-60 -60 120 120"
      class="absolute inset-0 size-full rounded-full text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1DAAFF]"
      :class="disabled ? 'cursor-not-allowed' : dragging ? 'cursor-grabbing' : 'cursor-grab'"
      style="touch-action: none"
      role="slider"
      :tabindex="disabled ? -1 : 0"
      :aria-label="label"
      aria-valuemin="0"
      aria-valuemax="359"
      :aria-valuenow="angle"
      :aria-valuetext="`${angle} degrees`"
      :aria-disabled="disabled || undefined"
      @pointerdown="down"
      @pointermove="move"
      @pointerup="up"
      @pointercancel="up"
      @keydown="key"
    >
      <g :transform="`rotate(${angle})`">
        <line x1="0" y1="-52" x2="0" y2="52" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
        <!-- The first color's end is the handle; the second's is a smaller dot. -->
        <circle cx="0" cy="52" r="4" :fill="second" stroke="currentColor" stroke-width="2" />
        <circle cx="0" cy="-52" r="7" :fill="first" stroke="currentColor" stroke-width="2.5" />
      </g>
      <circle cx="0" cy="0" r="3.5" class="fill-background" stroke="currentColor" stroke-width="2" />
    </svg>
  </div>
</template>
