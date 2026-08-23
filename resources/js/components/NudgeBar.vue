<script setup lang="ts">
import type { Component } from 'vue';

// Nudge banner from the "Nudge bar" design canvas: a full-bleed radial wash
// anchored on the left, with a pill CTA. `warn` is the red flavor (disabled
// lists, unassigned alerts), `good` the green one (celebratory nudges).
withDefaults(
  defineProps<{
    variant?: 'warn' | 'good';
    title: string;
    body: string;
    /** A short text glyph ("!") or an imported lucide icon component. */
    icon: string | Component;
    buttonText: string;
  }>(),
  { variant: 'warn' },
);

defineEmits<{ click: [] }>();
</script>

<template>
  <div class="nudge-bar flex flex-wrap items-center gap-4 px-6 py-5" :class="variant">
    <div
      class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] text-xl font-bold dark:text-white"
      :class="variant === 'good' ? 'bg-green-500/25 text-green-700' : 'bg-red-500/25 text-red-700'"
    >
      <component :is="icon" v-if="typeof icon !== 'string'" class="h-5 w-5" />
      <template v-else>{{ icon }}</template>
    </div>
    <div class="min-w-0 flex-1 text-sm leading-normal">
      <div class="font-semibold text-foreground dark:text-white">{{ title }}</div>
      <div class="text-foreground/70 dark:text-white/65">{{ body }}</div>
    </div>
    <button
      class="nudge-cta shrink-0 cursor-pointer rounded-full border border-black/30 bg-transparent px-4 py-1.5 text-xs font-medium text-foreground hover:bg-black/5 dark:border-white/55 dark:text-white dark:hover:border-white/80 dark:hover:bg-white/8"
      @click="$emit('click')"
    >
      {{ buttonText }}
    </button>
  </div>
</template>

<style scoped>
/* Soft radial wash anchored on the left: full-strength in dark mode (the
   design's signature look), a light tint with a colored hairline border in
   light mode. Per the design, the gradient deepens only while the CTA button
   is hovered - not on hovering the bar itself. */
.nudge-bar {
  border-radius: 24px;
}

.nudge-bar.warn {
  border: 1px solid rgb(239 68 68 / 0.25);
  background: radial-gradient(ellipse 60% 140% at 0% 50%, rgb(220 38 38 / 0.14) 0%, rgb(220 38 38 / 0.06) 35%, transparent 70%);
}
.nudge-bar.warn:has(.nudge-cta:hover) {
  background: radial-gradient(ellipse 70% 150% at 0% 50%, rgb(220 38 38 / 0.2) 0%, rgb(220 38 38 / 0.1) 40%, transparent 78%);
}
.nudge-bar.good {
  border: 1px solid rgb(34 197 94 / 0.25);
  background: radial-gradient(ellipse 60% 140% at 0% 50%, rgb(22 163 74 / 0.14) 0%, rgb(22 163 74 / 0.06) 35%, transparent 70%);
}
.nudge-bar.good:has(.nudge-cta:hover) {
  background: radial-gradient(ellipse 70% 150% at 0% 50%, rgb(22 163 74 / 0.2) 0%, rgb(22 163 74 / 0.1) 40%, transparent 78%);
}

.dark .nudge-bar.warn {
  border: 0;
  background: radial-gradient(
    ellipse 60% 140% at 0% 50%,
    rgb(220 38 38 / 0.55) 0%,
    rgb(220 38 38 / 0.28) 35%,
    rgb(30 18 22 / 0.9) 70%,
    rgb(18 15 20 / 0.95) 100%
  );
}
.dark .nudge-bar.warn:has(.nudge-cta:hover) {
  background: radial-gradient(
    ellipse 70% 150% at 0% 50%,
    rgb(239 68 68 / 0.7) 0%,
    rgb(220 38 38 / 0.38) 40%,
    rgb(30 18 22 / 0.9) 78%,
    rgb(18 15 20 / 0.95) 100%
  );
}
.dark .nudge-bar.good {
  border: 0;
  background: radial-gradient(
    ellipse 60% 140% at 0% 50%,
    rgb(22 163 74 / 0.5) 0%,
    rgb(22 163 74 / 0.22) 35%,
    rgb(16 24 20 / 0.9) 70%,
    rgb(15 20 16 / 0.95) 100%
  );
}
.dark .nudge-bar.good:has(.nudge-cta:hover) {
  background: radial-gradient(
    ellipse 70% 150% at 0% 50%,
    rgb(34 197 94 / 0.65) 0%,
    rgb(22 163 74 / 0.32) 40%,
    rgb(16 24 20 / 0.9) 78%,
    rgb(15 20 16 / 0.95) 100%
  );
}
</style>
