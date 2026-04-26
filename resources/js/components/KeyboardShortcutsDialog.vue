<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
  show: boolean;
  shortcuts: Array<{ id: string; description?: string; keys: string }>;
}>();

const emit = defineEmits<{ close: [] }>();

function handleEsc(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    emit('close');
  }
}

watch(
  () => props.show,
  (open) => {
    if (open) {
      window.addEventListener('keydown', handleEsc, true);
    } else {
      window.removeEventListener('keydown', handleEsc, true);
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleEsc, true);
});
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    @click.self="emit('close')"
  >
    <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-lg border border-sidebar bg-sidebar-accent shadow-lg md:max-w-2xl lg:max-w-4xl">
      <div class="flex shrink-0 items-center justify-between border-b border-sidebar px-6 py-4">
        <h3 class="text-lg font-medium">Keyboard Shortcuts</h3>
        <button @click.prevent="emit('close')" class="cursor-pointer rounded-full p-1 hover:bg-background">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor">
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto px-6 py-4">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="shortcut in shortcuts"
            :key="shortcut.id"
            class="flex items-center justify-between gap-3 rounded-md border p-2 text-sm"
          >
            <span class="truncate">{{ shortcut.description ?? shortcut.id }}</span>
            <kbd class="shrink-0 rounded bg-sidebar px-2 py-1 font-mono text-xs">{{ shortcut.keys }}</kbd>
          </div>
        </div>
        <p class="mt-4 text-xs text-muted-foreground">
          Press <kbd class="rounded bg-sidebar px-1">Ctrl+K</kbd> to toggle this dialog.
        </p>
      </div>
    </div>
  </div>
</template>
