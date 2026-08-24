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
 *
 * The whole row is the control: the ROW carries `role="switch"` and the
 * handler, and the track and thumb are inert `<span>`s. That gives one hit
 * target the width of the list, one tab stop, and no nested buttons. The row
 * deliberately has no hover or active styling - the switch flipping is the
 * only feedback, so nothing else moves when you click.
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
    <button
      v-for="(et, index) in eventTypes"
      :key="et.value"
      type="button"
      role="switch"
      :aria-checked="isEnabled(et.value)"
      :title="isEnabled(et.value) ? `Alerts fire on ${et.label}. Click to turn off.` : `Ignoring ${et.label}. Click to turn on.`"
      class="flex w-full cursor-pointer items-center gap-3 px-3 py-2.5 text-left"
      :class="index > 0 ? 'border-t border-sidebar-border' : ''"
      @click="toggle(et.value)"
    >
      <span class="min-w-0 flex-1 text-sm text-foreground">{{ et.label }}</span>
      <span
        aria-hidden="true"
        class="relative h-[17px] w-[30px] shrink-0 rounded-full border"
        :class="isEnabled(et.value) ? 'border-green-500/60 bg-green-500/25' : 'border-black/15 bg-black/5 dark:border-white/15 dark:bg-white/6'"
      >
        <span
          class="absolute top-[2px] h-[11px] w-[11px] rounded-full transition-all"
          :class="isEnabled(et.value) ? 'left-[16px] bg-green-600 dark:bg-green-400' : 'left-[2px] bg-black/40 dark:bg-white/40'"
        ></span>
      </span>
    </button>
  </div>
</template>
