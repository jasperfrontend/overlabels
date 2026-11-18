<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useEventListener, useResizeObserver } from '@vueuse/core';
import { ArrowRight, ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import ProductIcon from '@/components/product-icons/ProductIcon.vue';
import { hasProductIcon } from '@/components/product-icons/registry';
import ServiceLogo from '@/components/ServiceLogo.vue';
import { useEventColors } from '@/composables/useEventColors';
import type { InstalledProduct } from '@/types';

// One row of installed products on the dashboard: art, not a list. The
// dashboard shows two, games and everything else, so the row owns its own
// arrows and drag state.

const props = defineProps<{
  title: string;
  headingId: string;
  products: InstalledProduct[];
}>();

const ready = computed(() => props.products.filter((p) => (p.remaining ?? 0) === 0).length);

const { eventTypeDotClass } = useEventColors();
const serviceColor = (service: string) => eventTypeDotClass('product', service);

// The strip swipes on touch, but a mouse needs more than a thin scrollbar:
// arrows on hover, and click-and-drag, which is what people try first.
const strip = ref<HTMLElement | null>(null);
const canLeft = ref(false);
const canRight = ref(false);

function updateArrows() {
  const el = strip.value;
  if (!el) return;
  canLeft.value = el.scrollLeft > 1;
  canRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
}

useResizeObserver(strip, updateArrows);

function scrollStrip(direction: -1 | 1) {
  const el = strip.value;
  if (!el) return;
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Most of a screen, so the half-shown tile on the edge becomes the first one.
  el.scrollBy({ left: direction * el.clientWidth * 0.8, behavior: smooth ? 'smooth' : 'auto' });
}

const dragging = ref(false);
let dragStartX = 0;
let dragStartScroll = 0;
let dragPointer: number | null = null;
let dragMoved = false;

function onPointerDown(e: PointerEvent) {
  if (e.pointerType !== 'mouse' || e.button !== 0 || !strip.value) return;
  dragPointer = e.pointerId;
  dragStartX = e.clientX;
  dragStartScroll = strip.value.scrollLeft;
  dragMoved = false;
}

useEventListener(window, 'pointermove', (e: PointerEvent) => {
  if (e.pointerId !== dragPointer || !strip.value) return;
  const dx = e.clientX - dragStartX;
  if (!dragMoved && Math.abs(dx) < 6) return;
  dragMoved = true;
  dragging.value = true;
  strip.value.scrollLeft = dragStartScroll - dx;
});

useEventListener(window, 'pointerup', (e: PointerEvent) => {
  if (e.pointerId !== dragPointer) return;
  dragPointer = null;
  dragging.value = false;
});

// A drag that ends on a tile must not also open it.
function onClickCapture(e: MouseEvent) {
  if (!dragMoved) return;
  dragMoved = false;
  e.preventDefault();
  e.stopPropagation();
}
</script>

<template>
  <section class="flex flex-col gap-4" :aria-labelledby="headingId">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <h2 :id="headingId" class="text-2xl font-bold tracking-tight text-foreground">{{ title }}</h2>
      <Link
        v-if="products.length > 0"
        :href="route('products.index', { category: 'installed' })"
        class="inline-flex items-center gap-1 text-sm font-medium text-[#0A78BE] hover:underline dark:text-[#1DAAFF]"
      >
        {{ ready }} of {{ products.length }} ready
        <ArrowRight class="size-3.5" />
      </Link>
    </div>

    <div class="strip-wrap relative">
      <!-- Mouse-only extras: tab and arrow keys already reach every tile, so
           these stay out of the tab order. -->
      <button
        type="button"
        class="strip-arrow left-0 -translate-x-1/2"
        :class="{ 'is-on': canLeft }"
        tabindex="-1"
        aria-hidden="true"
        @click="scrollStrip(-1)"
      >
        <ChevronLeft class="size-6" />
      </button>
      <button
        type="button"
        class="strip-arrow right-0 translate-x-1/2"
        :class="{ 'is-on': canRight }"
        tabindex="-1"
        aria-hidden="true"
        @click="scrollStrip(1)"
      >
        <ChevronRight class="size-6" />
      </button>

      <ul
        ref="strip"
        class="stream-strip -mx-4 flex gap-4 overflow-x-auto px-4 pb-3 md:mx-0 md:px-0"
        :class="dragging ? 'is-dragging' : 'snap-x'"
        @scroll.passive="updateArrows"
        @pointerdown="onPointerDown"
        @click.capture="onClickCapture"
        @dragstart.prevent
      >
        <li v-for="product in products" :key="product.slug" class="w-60 shrink-0 snap-start">
          <Link
            :href="route('products.manage', product.slug)"
            class="tile group flex h-full flex-col border border-border bg-sidebar-accent transition-all duration-200 hover:-translate-y-1 hover:border-[#1DAAFF]"
          >
            <div class="relative aspect-video overflow-hidden border-b border-border">
              <img
                v-if="product.hero"
                :src="product.hero"
                alt=""
                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
              />
              <div
                v-else
                class="flex size-full items-center justify-center bg-muted/30"
                :class="product.service ? serviceColor(product.service) : ''"
              >
                <ServiceLogo v-if="product.service" :source="product.service" class="size-12" />
              </div>
            </div>
            <div class="flex flex-1 items-center gap-3 p-3">
              <ProductIcon v-if="hasProductIcon(product.slug)" :slug="product.slug" class="size-5 shrink-0 text-foreground" />
              <ServiceLogo v-else-if="product.service" :source="product.service" class="size-5 shrink-0" :class="serviceColor(product.service)" />
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ product.name }}</p>
                <p v-if="(product.remaining ?? 0) === 0" class="inline-flex items-center gap-1.5 text-xs text-green-700 dark:text-green-400">
                  <span class="size-1.5 rounded-full bg-green-500" />
                  Ready
                </p>
                <p v-else class="inline-flex items-center gap-1.5 text-xs text-fuchsia-700 dark:text-fuchsia-400">
                  <span class="size-1.5 rounded-full bg-fuchsia-500" />
                  {{ product.remaining === 1 ? 'One step left' : `${product.remaining} steps left` }}
                </p>
              </div>
            </div>
          </Link>
        </li>

        <li class="w-60 shrink-0 snap-start">
          <Link
            :href="route('products.index')"
            class="group flex h-full min-h-40 flex-col items-center justify-center gap-2 border border-dashed border-border text-muted-foreground transition-colors hover:border-[#1DAAFF] hover:text-foreground"
          >
            <Plus class="size-6 transition-transform group-hover:rotate-90" />
            <span class="text-sm font-medium">Find something new</span>
          </Link>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
/* A row you can swipe, not a scrollbar you have to look at. */
.stream-strip {
  scrollbar-width: thin;
  scrollbar-color: color-mix(in oklab, #1daaff 45%, transparent) transparent;
}

.stream-strip.is-dragging {
  cursor: grabbing;
  user-select: none;
}

/* Square, bordered, the page's hard shadow: the arrows belong to the app,
   not to a carousel library. Hidden until the strip is hovered, and only
   where there is more to see in that direction. */
.strip-arrow {
  position: absolute;
  top: calc(50% - 6px);
  z-index: 10;
  display: none;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  margin-top: -1.375rem;
  border: 2px solid color-mix(in oklab, var(--foreground) 90%, transparent);
  background: var(--background);
  color: var(--foreground);
  box-shadow: 4px 4px 0 0 #1daaff;
  cursor: pointer;
  opacity: 0;
  pointer-events: none;
  transition:
    opacity 0.15s ease,
    border-color 0.15s ease;
}

.strip-arrow:hover {
  border-color: #1daaff;
}

/* Only where there is a real pointer to hover with; touch keeps the swipe. */
@media (hover: hover) and (pointer: fine) {
  .strip-arrow {
    display: flex;
  }
  .strip-wrap:hover .strip-arrow.is-on {
    opacity: 1;
    pointer-events: auto;
  }
}

.tile:hover {
  box-shadow: 4px 4px 0 0 #1daaff;
}

@media (prefers-reduced-motion: reduce) {
  .tile,
  .tile * {
    transition: none !important;
  }
}
</style>
