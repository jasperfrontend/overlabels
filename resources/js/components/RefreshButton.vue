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
        'btn',
        variantClass || '',
        isLoading && 'opacity-50 cursor-not-allowed',
      ]"
    >
      <slot />
      <span>{{ label }}</span>
    </button>
  </form>
</template>
