<template>
  <div
    v-if="visible"
    class="fixed top-0 left-0 z-50 w-full text-center rounded-none shadow-xl px-4 py-3 border flex items-start gap-3 text-sm"
    :class="[
      color.bg,
      color.text
    ]"
    @mouseover="pauseTimeout"
    @mouseleave="resumeTimeout"
  >
    <component :is="icon" class="w-5 h-5 mt-0.5 shrink-0" />
    <span class="flex-1">{{ message }}</span>
    <button @click="dismiss" class="text-xl leading-none cursor-pointer">&times;</button>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { CheckCircle, AlertTriangle, Info, XCircle } from 'lucide-vue-next'

const props = defineProps({
  message: { type: String, required: true },
  type: { type: String, default: 'info' }, // info | success | warning | error
  duration: { type: Number, default: 4000 }
})

const visible = ref(true)
let timeout = null

const dismiss = () => {
  visible.value = false
}

const pauseTimeout = () => {
  clearTimeout(timeout)
}

const resumeTimeout = () => {
  timeout = setTimeout(dismiss, props.duration)
}

watch(
  () => props.message,
  (newVal) => {
    if (newVal) {
      visible.value = true
      clearTimeout(timeout)
      timeout = setTimeout(dismiss, props.duration)
    }
  },
  { immediate: true }
)

const icon = computed(() => {
  switch (props.type) {
    case 'success': return CheckCircle
    case 'warning': return AlertTriangle
    case 'error': return XCircle
    default: return Info
  }
})

const color = computed(() => {
  switch (props.type) {
    case 'success':
      return {
        bg: 'bg-green-50',
        border: 'border-green-200',
        text: 'text-green-900'
      }
    case 'error':
      return {
        bg: 'bg-red-50',
        border: 'border-red-200',
        text: 'text-red-900'
      }
    case 'warning':
      return {
        bg: 'bg-yellow-50',
        border: 'border-yellow-200',
        text: 'text-yellow-900'
      }
    default:
      return {
        bg: 'bg-blue-50',
        border: 'border-blue-200',
        text: 'text-blue-900'
      }
  }
})
</script>
