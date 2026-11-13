<script setup lang="ts">
import { Check } from '@lucide/vue';

/**
 * One node on a product page's rail: the dot on the left, the line down to
 * the next node, and whatever the step says in the slot. The node is the
 * step's state, so the content never has to carry a border or a band to say
 * it: a tick for done, the fuchsia ring for the one thing to do now, a hollow
 * ring for a step still ahead, and a violet ring (with the step's own icon)
 * for an optional extra.
 *
 *   done     - finished, green tick; `ticked` lands the tick with a pop
 *   current  - the step to do now; the page's one glow
 *   open     - still to do, but not the one to do first
 *   upcoming - known to come later, not doable yet; greyed by the caller
 *   extra    - optional, available
 *   later    - optional, available after install
 */
withDefaults(
  defineProps<{
    state: 'done' | 'current' | 'open' | 'upcoming' | 'extra' | 'later';
    /** No line under the last node of a group. */
    last?: boolean;
    ticked?: boolean;
  }>(),
  { last: false, ticked: false },
);
</script>

<template>
  <li class="grid grid-cols-[28px_minmax(0,1fr)] gap-x-4 sm:gap-x-5">
    <div class="flex flex-col items-center">
      <span
        v-if="state === 'done'"
        class="flex size-7 shrink-0 items-center justify-center rounded-full bg-green-500 text-white"
        :class="{ 'rail-tick': ticked }"
        aria-hidden="true"
      >
        <Check class="size-4" stroke-width="3" />
      </span>
      <span
        v-else-if="state === 'current'"
        class="rail-current flex size-7 shrink-0 items-center justify-center rounded-full border-2 border-fuchsia-500 bg-background"
        aria-hidden="true"
      >
        <span class="size-2.5 rounded-full bg-fuchsia-500 dark:bg-fuchsia-400" />
      </span>
      <span
        v-else-if="state === 'extra' || state === 'later'"
        class="flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-violet-600 dark:text-violet-300"
        :class="state === 'later' ? 'border-violet-500/45 opacity-60 dark:border-violet-400/45' : 'border-violet-500 dark:border-violet-400'"
        aria-hidden="true"
      >
        <slot name="icon" />
      </span>
      <span v-else class="size-7 shrink-0 rounded-full border-2 border-sidebar-border" aria-hidden="true" />
      <span v-if="!last" class="min-h-4 w-0.5 flex-1" :class="state === 'done' ? 'bg-green-500/55' : 'bg-sidebar-border'" />
    </div>
    <div class="flex min-w-0 flex-col items-start gap-2.5" :class="last ? 'pb-2' : 'pb-8'">
      <slot />
    </div>
  </li>
</template>

<style scoped>
/* The current step's glow: the same fuchsia, and the same breathing, as
   [data-product-focus] in app.css, on the node instead of a box. */
.rail-current {
  box-shadow:
    0 0 0 6px rgb(217 70 239 / 0.18),
    0 0 22px rgb(217 70 239 / 0.5);
  animation: rail-current-glow 2.4s ease-in-out infinite;
}

@keyframes rail-current-glow {
  0%,
  100% {
    box-shadow:
      0 0 0 6px rgb(217 70 239 / 0.18),
      0 0 22px rgb(217 70 239 / 0.5);
  }
  50% {
    box-shadow:
      0 0 0 4px rgb(217 70 239 / 0.1),
      0 0 12px rgb(217 70 239 / 0.3);
  }
}

.rail-tick {
  animation: rail-tick-land 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rail-tick-land {
  from {
    transform: scale(0.4);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .rail-current,
  .rail-tick {
    animation: none;
  }
}
</style>
