<script lang="ts">
/**
 * A product's own mark: the simplest drawing of the thing that still reads
 * as it. Single tint (currentColor), 24x24, square corners like the rest of
 * the UI. Where a product is too small to carry its screenshot (the
 * dashboard's installed products box), this stands in for it.
 *
 * Keyed by product slug. A product without a mark here has none, and the
 * caller falls back to its service logo or hero.
 */
interface Shape {
  rects?: { x: number; y: number; w: number; h: number }[];
  dots?: { cx: number; cy: number; r: number }[];
  rings?: { cx: number; cy: number; r: number }[];
}

const MARKS: Record<string, Shape> = {
  // Five blocks, each nudged off the one below, the top one hanging most of
  // the way off the side: a tower one block from toppling.
  'chat-tower': {
    rects: [
      { x: 4, y: 18.8, w: 11, h: 3.2 },
      { x: 5.5, y: 14.7, w: 11, h: 3.2 },
      { x: 4.5, y: 10.6, w: 11, h: 3.2 },
      { x: 6.5, y: 6.5, w: 11, h: 3.2 },
      { x: 12.5, y: 2.4, w: 11, h: 3.2 },
    ],
  },
  // The globe and one pin on it.
  'chat-checkin': {
    rings: [{ cx: 12, cy: 12, r: 9 }],
    dots: [{ cx: 14.5, cy: 9.5, r: 2.5 }],
  },
  // The rack seen from above: 4-3-2-1.
  'follower-bowling': {
    dots: [
      { cx: 5.4, cy: 6, r: 1.7 },
      { cx: 9.8, cy: 6, r: 1.7 },
      { cx: 14.2, cy: 6, r: 1.7 },
      { cx: 18.6, cy: 6, r: 1.7 },
      { cx: 7.6, cy: 10, r: 1.7 },
      { cx: 12, cy: 10, r: 1.7 },
      { cx: 16.4, cy: 10, r: 1.7 },
      { cx: 9.8, cy: 14, r: 1.7 },
      { cx: 14.2, cy: 14, r: 1.7 },
      { cx: 12, cy: 18, r: 1.7 },
    ],
  },
  // Three bubbles of different sizes, floating apart.
  'chat-emote-bubbles': {
    rings: [
      { cx: 7.5, cy: 15.5, r: 4.5 },
      { cx: 16.5, cy: 7, r: 3 },
      { cx: 18, cy: 18.5, r: 2 },
    ],
  },
  // Five chat lines, each ending at its own width.
  'twitch-chat-overlay': {
    rects: [
      { x: 3, y: 3, w: 15, h: 2 },
      { x: 3, y: 7.5, w: 10, h: 2 },
      { x: 3, y: 12, w: 18, h: 2 },
      { x: 3, y: 16.5, w: 7, h: 2 },
      { x: 3, y: 21, w: 13, h: 2 },
    ],
  },
};

export function hasProductIcon(slug: string): boolean {
  return slug in MARKS;
}
</script>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  slug: string;
}>();

const mark = computed(() => MARKS[props.slug] ?? null);
</script>

<template>
  <svg v-if="mark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <rect v-for="(r, i) in mark.rects ?? []" :key="`r${i}`" :x="r.x" :y="r.y" :width="r.w" :height="r.h" />
    <circle v-for="(d, i) in mark.dots ?? []" :key="`d${i}`" :cx="d.cx" :cy="d.cy" :r="d.r" />
    <circle v-for="(c, i) in mark.rings ?? []" :key="`c${i}`" :cx="c.cx" :cy="c.cy" :r="c.r" fill="none" stroke="currentColor" stroke-width="2" />
  </svg>
</template>
