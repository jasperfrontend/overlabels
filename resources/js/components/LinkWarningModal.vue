<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs">
    <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl max-w-sm w-full">
      <p class="text-sm text-gray-800 dark:text-gray-200 mb-4">{{ warningText }}</p>
      <div class="flex justify-end space-x-3">
        <button
          ref="cancelButton"
          @click="reset"
          class="px-4 py-2 text-sm cursor-pointer rounded-2xl transition hover:bg-accent/80 hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-700">Cancel</button>
        <button

          @click="confirm"
          class="px-4 py-2 bg-red-600/40 ring-red-700 hover:bg-red-600/50 rounded-2xl transition text-white cursor-pointer hover:ring-2 hover:ring-red-300 dark:hover:ring-red-700">Continue</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useLinkWarning } from '@/composables/useLinkWarning'
import { nextTick, watch, ref, onBeforeUnmount } from 'vue';

const { show, warningText, confirm, reset } = useLinkWarning()
const cancelButton = ref<HTMLButtonElement | null>(null)
function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    reset()
  }
}

watch(show, async (visible) => {
  const body = document.querySelector('body')
  if (visible) {
    window.addEventListener('keydown', onEscape)
    body?.classList.add('overflow-hidden')
    await nextTick()
    cancelButton.value?.focus()
  } else {
    window.removeEventListener('keydown', onEscape)
    body?.classList.remove('overflow-hidden')
  }
})

// Safety cleanup if the component is ever unmounted
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onEscape)
})
</script>
