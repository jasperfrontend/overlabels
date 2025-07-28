<!-- NotificationToast.vue (Enhanced version) -->
<template>
  <ToastRoot
    :duration="notification.duration"
    :type="notification.type === 'error' ? 'foreground' : 'background'"
    :open="isOpen"
    @update:open="handleOpenChange"
    class="fixed top-4 right-4 z-50 w-full max-w-xs rounded-2xl shadow-xl border transition-all duration-300 ease-in-out transform"
    :class="[
      colorClasses.bg,
      colorClasses.border,
      colorClasses.text,
      isOpen ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'
    ]"
    data-testid="notification-toast"
  >
    <div class="flex items-start gap-3 p-4">
      <component 
        :is="iconComponent" 
        class="w-5 h-5 mt-0.5 shrink-0"
        :class="colorClasses.icon"
      />
      
      <div class="flex-1 min-w-0">
        <ToastTitle 
          v-if="notification.title" 
          class="font-medium text-sm mb-1"
        >
          {{ notification.title }}
        </ToastTitle>
        
        <ToastDescription class="text-sm">
          {{ notification.message }}
        </ToastDescription>
        
        <ToastAction
          v-if="notification.action"
          class="mt-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded border"
          :class="[
            colorClasses.actionBg,
            colorClasses.actionBorder,
            colorClasses.actionText
          ]"
          :alt-text="notification.action.altText || 'Perform action'"
          @click="handleAction"
        >
          {{ notification.action.label }}
        </ToastAction>
      </div>
      
      <ToastClose 
        class="text-xl leading-none hover:opacity-70 transition-opacity"
        aria-label="Close notification"
      >
        &times;
      </ToastClose>
    </div>
    
    <!-- Progress bar for remaining time -->
    <div 
      v-if="showProgress && remaining > 0"
      class="absolute bottom-0 left-0 h-1 bg-current opacity-20 transition-all duration-100"
      :style="{ width: `${(remaining / notification.duration) * 100}%` }"
    />
  </ToastRoot>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { ToastRoot, ToastTitle, ToastDescription, ToastAction, ToastClose } from 'reka-ui'
import { CheckCircle, AlertTriangle, Info, XCircle } from 'lucide-vue-next'

const props = defineProps({
  notification: {
    type: Object,
    required: true,
    validator: (notification) => {
      return notification && typeof notification.message === 'string'
    }
  },
  showProgress: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['dismiss', 'action'])

const isOpen = ref(true)
const remaining = ref(props.notification.duration)
let interval = null

onMounted(() => {
  if (props.showProgress) {
    interval = setInterval(() => {
      remaining.value = Math.max(0, remaining.value - 100)
    }, 100)
  }
})

onUnmounted(() => {
  if (interval) {
    clearInterval(interval)
  }
})

const handleOpenChange = (open) => {
  isOpen.value = open
  if (!open) {
    emit('dismiss', props.notification.id)
  }
}

const handleAction = () => {
  if (props.notification.action?.handler) {
    props.notification.action.handler()
  }
  emit('action', {
    notificationId: props.notification.id,
    action: props.notification.action
  })
  // Optionally close after action
  if (props.notification.action?.closeAfter !== false) {
    isOpen.value = false
  }
}

const iconComponent = computed(() => {
  switch (props.notification.type) {
    case 'success': return CheckCircle
    case 'warning': return AlertTriangle
    case 'error': return XCircle
    default: return Info
  }
})

const colorClasses = computed(() => {
  switch (props.notification.type) {
    case 'success':
      return {
        bg: 'bg-green-50',
        border: 'border-green-200',
        text: 'text-green-900',
        icon: 'text-green-600',
        actionBg: 'bg-green-100 hover:bg-green-200',
        actionBorder: 'border-green-300',
        actionText: 'text-green-800'
      }
    case 'error':
      return {
        bg: 'bg-red-50',
        border: 'border-red-200',
        text: 'text-red-900',
        icon: 'text-red-600',
        actionBg: 'bg-red-100 hover:bg-red-200',
        actionBorder: 'border-red-300',
        actionText: 'text-red-800'
      }
    case 'warning':
      return {
        bg: 'bg-yellow-50',
        border: 'border-yellow-200',
        text: 'text-yellow-900',
        icon: 'text-yellow-600',
        actionBg: 'bg-yellow-100 hover:bg-yellow-200',
        actionBorder: 'border-yellow-300',
        actionText: 'text-yellow-800'
      }
    default:
      return {
        bg: 'bg-blue-50',
        border: 'border-blue-200',
        text: 'text-blue-900',
        icon: 'text-blue-600',
        actionBg: 'bg-blue-100 hover:bg-blue-200',
        actionBorder: 'border-blue-300',
        actionText: 'text-blue-800'
      }
  }
})
</script>