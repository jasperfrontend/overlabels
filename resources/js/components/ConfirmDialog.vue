<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs" @click.self="cancel">
    <div
      role="alertdialog"
      aria-modal="true"
      :aria-labelledby="options.title ? 'confirm-dialog-title' : undefined"
      aria-describedby="confirm-dialog-message"
      class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
    >
      <h2 v-if="options.title" id="confirm-dialog-title" class="mb-2 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ options.title }}
      </h2>
      <p id="confirm-dialog-message" class="mb-4 text-sm whitespace-pre-line text-gray-800 dark:text-gray-200">{{ options.message }}</p>
      <div class="flex justify-end space-x-3">
        <button v-if="options.variant === 'confirm'" ref="cancelButton" @click="cancel" class="btn btn-sm btn-chill">
          {{ options.cancelLabel }}
        </button>
        <button ref="acceptButton" @click="accept" :class="['btn', options.tone === 'danger' ? 'btn-sm btn-danger' : 'btn-sm btn-primary']">
          {{ options.confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { nextTick, watch, ref, onBeforeUnmount } from 'vue';

const { show, options, accept, cancel } = useConfirm();

const cancelButton = ref<HTMLButtonElement | null>(null);
const acceptButton = ref<HTMLButtonElement | null>(null);

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    cancel();
  }
}

watch(show, async (visible) => {
  const body = document.querySelector('body');
  if (visible) {
    window.addEventListener('keydown', onEscape);
    body?.classList.add('overflow-hidden');
    await nextTick();
    // Focus the safe option, not the destructive one. An alert has no cancel
    // button, so its single OK is the safe option by default.
    (cancelButton.value ?? acceptButton.value)?.focus();
  } else {
    window.removeEventListener('keydown', onEscape);
    body?.classList.remove('overflow-hidden');
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onEscape);
  document.querySelector('body')?.classList.remove('overflow-hidden');
  // An open dialog whose host is unmounting must answer, or the caller hangs.
  if (show.value) cancel();
});
</script>
