<script setup lang="ts">
/**
 * One row per event type with a switch, for the "Alert on" section of the
 * donation integration settings pages.
 *
 * Replaces a row of `.btn-*` chips. A chip that is OFF still looks like a
 * button you are meant to press, so the two states read as "button" and
 * "button", and a wrapped row of them has no reading order. A labelled row
 * with a switch says which way each one is set without having to compare it
 * against its neighbours.
 *
 * The switch markup is the same one the Lists edit page uses for its chat
 * action permissions - deliberately, so the app has one switch, not two.
 */
defineProps<{
  eventTypes: Array<{ value: string; label: string }>;
}>();

const enabled = defineModel<string[]>({ required: true });

function isEnabled(value: string): boolean {
  return enabled.value.includes(value);
}

function toggle(value: string) {
  enabled.value = isEnabled(value) ? enabled.value.filter((v) => v !== value) : [...enabled.value, value];
}
</script>

<template>
  <div class="border border-sidebar-border">
    <div
      v-for="(et, index) in eventTypes"
      :key="et.value"
      class="flex items-center gap-3 px-3 py-2.5 hover:bg-black/3 dark:hover:bg-white/3"
      :class="index > 0 ? 'border-t border-sidebar-border' : ''"
    >
      <span class="min-w-0 flex-1 text-sm text-foreground">{{ et.label }}</span>
      <button
        type="button"
        role="switch"
        :aria-checked="isEnabled(et.value)"
        :aria-label="et.label"
        :title="isEnabled(et.value) ? `Alerts fire on ${et.label}. Click to turn off.` : `Ignoring ${et.label}. Click to turn on.`"
        class="relative h-[17px] w-[30px] shrink-0 cursor-pointer rounded-full border"
        :class="isEnabled(et.value) ? 'border-green-500/60 bg-green-500/25' : 'border-black/15 bg-black/5 dark:border-white/15 dark:bg-white/6'"
        @click="toggle(et.value)"
      >
        <span
          class="absolute top-[2px] h-[11px] w-[11px] rounded-full transition-all"
          :class="isEnabled(et.value) ? 'left-[16px] bg-green-600 dark:bg-green-400' : 'left-[2px] bg-black/40 dark:bg-white/40'"
        ></span>
      </button>
    </div>
  </div>
</template>
