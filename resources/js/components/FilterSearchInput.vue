<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { focusStart } from '@/composables/useFocusStart';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

withDefaults(
  defineProps<{
    label?: string;
    placeholder?: string;
    inputId?: string;
  }>(),
  { label: 'Search', placeholder: '', inputId: 'filter-search' },
);

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ search: [] }>();

const input = ref<HTMLInputElement | null>(null);

// Escape leaves the box the way Alt+S entered it: focus goes back to the
// page's Tab starting point (the list, or the main landmark), so the next Tab
// walks into the list and not back to the top of the sidebar. A page with
// neither just drops focus from the box.
function leave(): void {
  if (!focusStart()) input.value?.blur();
}

// Alt+S puts the cursor in this box from anywhere on the page, next to Alt+H
// for help and Alt+R for the reference (Alt reaches for a panel or a box).
// The box registers the shortcut itself, so every page that renders one has
// it, the Ctrl+K dialog lists it only there, and a page without a search box
// never advertises it. A modifier shortcut fires inside other inputs too, so
// it works while typing elsewhere; the text is selected so typing replaces it.
const { register } = useKeyboardShortcuts();

onMounted(() => {
  register(
    'focus-search',
    'alt+s',
    () => {
      input.value?.focus();
      input.value?.select();
    },
    { description: 'Search this page' },
  );
});
</script>

<template>
  <div class="flex flex-col gap-1">
    <label :for="inputId">{{ label }}</label>
    <input
      ref="input"
      :id="inputId"
      v-model="model"
      type="text"
      :placeholder="placeholder"
      class="input-border h-10 w-full"
      @input="emit('search')"
      @keydown.esc.prevent="leave"
    />
  </div>
</template>
