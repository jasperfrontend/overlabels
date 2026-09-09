<script setup lang="ts">
import { useUiMode } from '@/composables/useUiMode';

// The frame around the whole app while a product install is mid-way. You are
// in the flow or you are not, and this is what "in" looks like: a 10px glowing
// edge on every app page until the flow is fulfilled, fuchsia while steps
// remain, green once nothing is left. It is decoration only: no pointer
// events, no layout, nothing to click. The banner above the page carries the
// words and the way back.
const { mode, ready } = useUiMode();
</script>

<template>
  <div
    v-if="mode === 'product'"
    class="product-flow-frame pointer-events-none fixed inset-0 z-40 border-[10px]"
    :class="ready ? 'product-flow-frame-ready border-green-500' : 'border-fuchsia-500'"
    aria-hidden="true"
  >
    <!-- The frame names itself, bottom left, in the border's own colour. -->
    <span
      class="absolute bottom-0 left-0 px-3 py-1 font-mono text-[11px] font-semibold tracking-[0.16em] text-white uppercase"
      :class="ready ? 'bg-green-500' : 'bg-fuchsia-500'"
    >
      {{ ready ? 'Product installation complete' : 'Product installation mode enabled' }}
    </span>
  </div>
</template>

<style scoped>
.product-flow-frame {
  box-shadow:
    inset 0 0 40px rgb(217 70 239 / 0.55),
    inset 0 0 120px rgb(217 70 239 / 0.2);
  animation: product-flow-glow 2.4s ease-in-out infinite;
}

.product-flow-frame-ready {
  box-shadow:
    inset 0 0 40px rgb(34 197 94 / 0.55),
    inset 0 0 120px rgb(34 197 94 / 0.2);
  animation: none;
}

@keyframes product-flow-glow {
  0%,
  100% {
    opacity: 0.85;
  }
  50% {
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .product-flow-frame {
    animation: none;
  }
}
</style>
