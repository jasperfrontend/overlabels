<script setup lang="ts">
import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
  action: string
  label: string
  variantClass?: string
}>()

const isLoading = ref(false)
const csrf = usePage().props.auth.csrf ?? document.querySelector('meta[name=csrf-token]')?.getAttribute('content')

const handleSubmit = (e: Event) => {
  e.preventDefault()
  isLoading.value = true
  router.post(props.action, {}, {
    onFinish: () => {
      isLoading.value = false
    }
  })
}
</script>

<template>
  <form @submit="handleSubmit">
    <input type="hidden" name="_token" :value="csrf" />
    <button
      type="submit"
      :disabled="isLoading"
      :class="[
        'flex cursor-pointer w-full border gap-4 justify-center items-center transition hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-700 hover:bg-accent/50 active:bg-accent p-3 mb-4 rounded-2xl',
        variantClass || 'bg-accent/20',
        isLoading && 'opacity-50 cursor-not-allowed',
      ]"
    >
      <slot />
      <span>{{ label }}</span>
    </button>
  </form>
</template>
