<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Check } from '@lucide/vue';
import type { ControlTypeMeta } from './controlTypeCatalog';

/**
 * One control type, sold. Renders the icon, the name, the one-line pitch and a
 * small demo of what the control actually looks like once it is live.
 *
 * Used twice: as a clickable card in the picker grid, and as a static summary
 * in the configure rail so the choice stays visible while the form is filled in.
 */
const props = withDefaults(
  defineProps<{
    meta: ControlTypeMeta;
    /** Renders as a button and reacts to hover/focus. Off for the rail. */
    selectable?: boolean;
    /** Accent border plus a check badge. The rail uses this to affirm the choice. */
    selected?: boolean;
  }>(),
  { selectable: false, selected: false },
);

// The timer demo actually ticks. A static "00:00:00" sells nothing; a clock
// that is visibly running tells you what you are getting in one glance.
const TIMER_BASE = 4 * 3600 + 32 * 60 + 9;
const elapsed = ref(0);
let ticker: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  if (props.meta.demo.kind !== 'timer') return;
  ticker = setInterval(() => {
    elapsed.value += 1;
  }, 1000);
});

onBeforeUnmount(() => {
  if (ticker) clearInterval(ticker);
});

const demoClock = computed(() => {
  const total = TIMER_BASE + elapsed.value;
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
});
</script>

<template>
  <component
    :is="selectable ? 'button' : 'div'"
    :type="selectable ? 'button' : undefined"
    :aria-pressed="selectable ? selected : undefined"
    class="relative flex w-full flex-col overflow-hidden border bg-background p-4 text-left transition duration-150"
    :class="[
      selected ? meta.accent.ringSelected : meta.accent.ring,
      selectable ? ['cursor-pointer', meta.accent.ringHover, meta.accent.ringFocus, 'focus-visible:ring-2 focus-visible:outline-none'] : '',
    ]"
  >
    <span
      v-if="selected"
      class="absolute top-3 right-3 flex size-5 items-center justify-center border"
      :class="[meta.accent.ring, meta.accent.text]"
      aria-hidden="true"
    >
      <Check class="size-3" />
    </span>

    <div class="relative flex items-start gap-3">
      <span class="flex size-10 shrink-0 items-center justify-center" :class="meta.accent.icon">
        <component :is="meta.icon" class="size-5" />
      </span>
      <div class="min-w-0 flex-1">
        <h3 class="text-base leading-tight font-semibold text-foreground">{{ meta.name }}</h3>
        <p class="mt-1 text-sm leading-snug text-foreground/75">{{ meta.tagline }}</p>
      </div>
    </div>

    <!-- Demo: what this looks like once it is on screen. -->
    <div class="relative mt-4 border border-border/50 bg-foreground/3 px-3 py-2.5 dark:bg-black/25">
      <template v-if="meta.demo.kind === 'text'">
        <span class="text-sm text-foreground">{{ meta.demo.value }}</span>
      </template>

      <template v-else-if="meta.demo.kind === 'stat'">
        <div class="flex items-baseline justify-between gap-3">
          <span class="text-xs tracking-wide text-muted-foreground uppercase">{{ meta.demo.label }}</span>
          <span class="font-mono text-lg leading-none font-semibold text-foreground">{{ meta.demo.value }}</span>
        </div>
      </template>

      <template v-else-if="meta.demo.kind === 'counter'">
        <div class="flex items-center justify-between gap-3">
          <span class="text-xs tracking-wide text-muted-foreground uppercase">{{ meta.demo.label }}</span>
          <span class="flex items-center gap-1.5">
            <span class="flex size-5 items-center justify-center border border-border/60 text-xs text-muted-foreground">-</span>
            <span class="min-w-8 text-center font-mono text-lg leading-none font-semibold text-foreground">{{ meta.demo.value }}</span>
            <span class="flex size-5 items-center justify-center border border-border/60 text-xs text-muted-foreground">+</span>
          </span>
        </div>
      </template>

      <template v-else-if="meta.demo.kind === 'timer'">
        <div class="flex items-center justify-between gap-3">
          <span class="text-xs tracking-wide text-muted-foreground uppercase">Subathon</span>
          <span class="font-mono text-lg leading-none font-semibold text-foreground tabular-nums">{{ demoClock }}</span>
        </div>
      </template>

      <template v-else-if="meta.demo.kind === 'datetime'">
        <div class="flex items-baseline justify-between gap-3">
          <span class="text-xs tracking-wide text-muted-foreground uppercase">Next stream</span>
          <span class="text-sm font-medium text-foreground">{{ meta.demo.value }}</span>
        </div>
      </template>

      <template v-else-if="meta.demo.kind === 'boolean'">
        <div class="flex items-center justify-between gap-3">
          <span class="text-sm text-foreground">{{ meta.demo.label }}</span>
          <span class="relative flex h-5 w-9 shrink-0 items-center rounded-full bg-violet-500/70 dark:bg-violet-400/60">
            <span class="absolute right-0.5 size-4 rounded-full bg-white" />
          </span>
        </div>
      </template>

      <template v-else-if="meta.demo.kind === 'formula'">
        <div class="space-y-1.5">
          <code class="block truncate font-mono text-[11px] text-muted-foreground">{{ meta.demo.expression }}</code>
          <div class="flex items-baseline justify-between gap-3">
            <span class="text-xs tracking-wide text-muted-foreground uppercase">Win rate</span>
            <span class="font-mono text-lg leading-none font-semibold text-foreground">{{ meta.demo.result }}%</span>
          </div>
        </div>
      </template>

      <template v-else-if="meta.demo.kind === 'pipe'">
        <div class="flex items-center gap-2 overflow-hidden">
          <code class="truncate font-mono text-[11px] text-muted-foreground">{{ meta.demo.from }}</code>
          <span class="shrink-0 text-muted-foreground">&rarr;</span>
          <span class="truncate text-xs text-foreground">{{ meta.demo.to }}</span>
        </div>
      </template>
    </div>
  </component>
</template>
