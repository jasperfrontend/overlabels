<script setup lang="ts">
import { X } from '@lucide/vue';

// One switched-on trigger in the Triggers tab. Switching off is the remove
// button: an alert's active list only ever shows what fires it.
const durationMs = defineModel<number>('durationMs', { required: true });
/** Variant condition. null = base/any amount; otherwise 'at_least' | 'exactly'. */
const conditionType = defineModel<string | null>('conditionType', { default: null });
const conditionValue = defineModel<number | null>('conditionValue', { default: null });

defineProps<{
  /** Human label rendered as the main row text. */
  label: string;
  /** Where the event comes from: "Twitch", "Ko-fi". */
  source: string;
  /** Monospace key text (e.g. `channel.follow` or `kofi:donation`). */
  keyText: string;
  /** When set (e.g. "bits"), this event supports a variant condition and the picker is shown. */
  amountUnit?: string;
}>();

const emit = defineEmits<{
  /** A finished change: duration or condition committed. */
  save: [];
  remove: [];
}>();

function clampSeconds(value: number): number {
  if (!Number.isFinite(value)) return 1;
  return Math.min(999, Math.max(1, Math.round(value)));
}

function onDurationInput(raw: string) {
  durationMs.value = clampSeconds(Number(raw) || 1) * 1000;
}

function onConditionTypeChange(raw: string) {
  // Branch on `next`, never on conditionType.value: a defineModel read right
  // after a write returns the parent's prop, which has not caught up yet.
  const next = raw === '' ? null : raw;
  conditionType.value = next;
  if (next === null) {
    conditionValue.value = null;
  } else if (conditionValue.value == null || conditionValue.value < 1) {
    conditionValue.value = 1;
  }
  emit('save');
}

function onConditionValueInput(raw: string) {
  conditionValue.value = Math.max(1, Math.round(Number(raw) || 1));
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border border-sidebar-border bg-sidebar-accent px-3 py-2.5">
    <div class="flex min-w-0 flex-[1_1_14rem] flex-col">
      <div class="flex min-w-0 items-center gap-2">
        <span class="truncate text-sm font-semibold text-foreground">{{ label }}</span>
        <span class="shrink-0 border border-violet-400/50 px-2 py-px text-[11px] text-muted-foreground">{{ source }}</span>
      </div>
      <span class="truncate font-mono text-[11px] text-muted-foreground">{{ keyText }}</span>
    </div>

    <div class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-2">
      <div v-if="amountUnit" class="flex items-center gap-2">
        <select
          :value="conditionType ?? ''"
          class="input-border h-9 cursor-pointer"
          aria-label="Amount filter"
          @change="onConditionTypeChange(($event.target as HTMLSelectElement).value)"
        >
          <option value="">Any amount</option>
          <option value="at_least">At least</option>
          <option value="exactly">Exactly</option>
        </select>
        <template v-if="conditionType">
          <input
            :value="conditionValue ?? 1"
            type="number"
            min="1"
            step="1"
            class="input-border h-9 w-24"
            aria-label="Amount"
            @input="onConditionValueInput(($event.target as HTMLInputElement).value)"
            @blur="emit('save')"
            @keydown.enter.prevent="emit('save')"
          />
          <span class="text-xs text-muted-foreground">{{ amountUnit }}</span>
        </template>
      </div>

      <label class="flex items-center gap-2 text-xs text-muted-foreground">
        Show
        <input
          :value="durationMs / 1000"
          type="number"
          min="1"
          max="999"
          step="1"
          class="input-border h-9 w-18"
          aria-label="Seconds on screen"
          @input="onDurationInput(($event.target as HTMLInputElement).value)"
          @blur="emit('save')"
          @keydown.enter.prevent="emit('save')"
        />
        sec
      </label>

      <button
        type="button"
        class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center border border-violet-400/50 text-muted-foreground transition-colors hover:border-red-500 hover:text-red-500"
        :aria-label="`Remove ${label}`"
        :title="`Remove ${label}`"
        @click="emit('remove')"
      >
        <X :size="14" />
      </button>
    </div>
  </div>
</template>
