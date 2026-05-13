<script setup lang="ts">
const enabled = defineModel<boolean>('enabled', { required: true });
const durationMs = defineModel<number>('durationMs', { required: true });

defineProps<{
  /** Human label rendered as the main row text. */
  label: string;
  /** Monospace key text shown under the label (e.g. `channel.follow` or `kofi:donation`). */
  keyText: string;
  /** Custom Tailwind classes for the toggle's checked color, so external groups can differ from Twitch. */
  toggleCheckedClass?: string;
}>();

const emit = defineEmits<{
  /** Save the row (fired when the toggle changes or duration commits). */
  save: [];
}>();

function clampSeconds(value: number): number {
  if (!Number.isFinite(value)) return 1;
  return Math.min(999, Math.max(1, Math.round(value)));
}

function onDurationInput(raw: string) {
  durationMs.value = clampSeconds(Number(raw) || 1) * 1000;
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 rounded-sm border border-sidebar-border bg-sidebar-accent p-3">
    <label class="relative inline-flex cursor-pointer items-center" :title="enabled ? 'Disable' : 'Enable'">
      <input
        v-model="enabled"
        type="checkbox"
        class="peer sr-only"
        @change="emit('save')"
      />
      <span
        class="peer h-6 w-10 rounded-full bg-gray-300 after:absolute after:inset-s-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-4 peer-focus:outline-none dark:bg-gray-600 dark:after:bg-gray-100"
        :class="toggleCheckedClass ?? 'peer-checked:bg-green-400 dark:peer-checked:bg-green-800'"
      />
    </label>

    <div class="min-w-0 flex-1">
      <div class="font-medium text-foreground">{{ label }}</div>
      <div class="font-mono text-xs text-muted-foreground">{{ keyText }}</div>
    </div>

    <div class="flex items-center gap-2" :class="{ 'opacity-40': !enabled }">
      <input
        :value="durationMs / 1000"
        type="number"
        min="1"
        max="999"
        step="1"
        class="input-border h-9 w-20 rounded-sm"
        :disabled="!enabled"
        @input="onDurationInput(($event.target as HTMLInputElement).value)"
        @blur="emit('save')"
        @keydown.enter.prevent="emit('save')"
      />
      <span class="text-xs text-muted-foreground">sec</span>
    </div>
  </div>
</template>
