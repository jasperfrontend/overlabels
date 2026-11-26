<script setup lang="ts">
/**
 * An on/off switch in the look of the list page's chat action switches
 * (pages/dashboard/lists/show.vue, "Chat actions"): same size, colours and
 * knob. A native button with role="switch", so it is a real control for
 * keyboards and screen readers.
 */
const model = defineModel<boolean>({ required: true });

defineProps<{
  /** The accessible name, when no visible label points at the switch. */
  label?: string;
  id?: string;
}>();
</script>

<template>
  <button
    :id="id"
    type="button"
    role="switch"
    :aria-checked="model"
    :aria-label="label"
    class="relative h-[17px] w-[30px] shrink-0 cursor-pointer rounded-full border"
    :class="model ? 'border-green-500/60 bg-green-500/25' : 'border-black/15 bg-black/5 dark:border-white/15 dark:bg-white/6'"
    @click="model = !model"
  >
    <span
      class="absolute top-[2px] h-[11px] w-[11px] rounded-full transition-all"
      :class="model ? 'left-[16px] bg-green-600 dark:bg-green-400' : 'left-[2px] bg-black/40 dark:bg-white/40'"
    ></span>
  </button>
</template>
