<script setup lang="ts">
defineProps<{
  show: boolean;
  shortcuts: Array<{ id: string; description?: string; keys: string }>;
}>();

const emit = defineEmits<{ close: [] }>();
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-md overflow-hidden rounded-lg border border-sidebar bg-sidebar-accent p-6 shadow-lg">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-medium">Keyboard Shortcuts</h3>
        <button @click.prevent="emit('close')" class="rounded-full p-1 hover:bg-background">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor">
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
      <div class="space-y-2">
        <div
          v-for="shortcut in shortcuts"
          :key="shortcut.id"
          class="flex items-center justify-between rounded-md border p-2 text-sm"
        >
          <span>{{ shortcut.description ?? shortcut.id }}</span>
          <kbd class="rounded bg-sidebar px-2 py-1 font-mono text-xs">{{ shortcut.keys }}</kbd>
        </div>
        <p class="mt-4 text-xs text-muted-foreground">
          Press <kbd class="rounded bg-sidebar px-1">Ctrl+K</kbd> to toggle this dialog.<br /><br />
          Shortcuts don't work when focused inside the code editor — click outside first.
        </p>
      </div>
    </div>
  </div>
</template>
