<template>
  <Transition name="toast" @after-leave="emit('dismiss')">
    <div
      v-if="visible"
      class="pointer-events-auto fixed top-0 left-0 z-50 flex w-full items-start px-4 py-3 gap-4 shadow-lg"
      :class="[color.bg, color.border]"
      :role="toastRole"
      :aria-live="ariaLive"
      @mouseenter="pauseTimeout"
      @mouseleave="resumeTimeout"
    >
      <component :is="icon" class="mt-0.5 h-5 w-5 shrink-0" :class="color.icon" aria-hidden="true" />

      <div class="min-w-0 flex-1">
        <p class="mb-1 text-sm leading-none font-semibold" :class="color.title">
          {{ color.label }}
        </p>
        <p class="text-sm leading-snug" :class="color.body">
          <span class="sr-only">{{ color.label }}: </span>
          {{ message }}
          <slot />
        </p>
      </div>

      <button
        type="button"
        @click="dismiss"
        class="shrink-0 cursor-pointer rounded text-2xl leading-none opacity-40 transition-opacity hover:opacity-80 focus:ring-2 focus:ring-black/20 focus:outline-none"
        :class="color.title"
        aria-label="Dismiss notification"
      >
        &times;
      </button>
    </div>
  </Transition>
</template>

<script lang="ts" setup>
import { AlertTriangle, Check, Info, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
  message: { type: String, required: true },
  type: { type: String, default: 'info' }, // info | success | warning | error
  duration: { type: Number, default: 3000 },
});

const emit = defineEmits<{ dismiss: [] }>();

const visible = ref(true);
let timeout: ReturnType<typeof setTimeout> | null = null;

const dismiss = () => {
  visible.value = false;
  // emit('dismiss') fires via @after-leave once the exit animation finishes
};

const pauseTimeout = () => {
  if (timeout) clearTimeout(timeout);
  timeout = null;
};

const resumeTimeout = () => {
  pauseTimeout();
  if (props.duration <= 0) return; // allow sticky toasts when duration is 0 or negative
  timeout = setTimeout(dismiss, props.duration);
};

onBeforeUnmount(() => pauseTimeout());

watch(
  () => props.message,
  (newVal) => {
    if (newVal) {
      visible.value = true;
      resumeTimeout();
    }
  },
  { immediate: true },
);

const icon = computed(() => {
  switch (props.type) {
    case 'success':
      return Check;
    case 'warning':
      return AlertTriangle;
    case 'error':
      return X;
    default:
      return Info;
  }
});

const ariaLive = computed(() => (props.type === 'error' ? 'assertive' : 'polite'));
const toastRole = computed(() => (props.type === 'error' ? 'alert' : 'status'));

const color = computed(() => {
  switch (props.type) {
    case 'success':
      return {
        bg: 'bg-violet-100',
        border: 'border-violet-200',
        icon: 'text-violet-600',
        title: 'text-violet-800',
        body: 'text-violet-700',
        label: 'Success',
      };
    case 'error':
      return {
        bg: 'bg-red-100',
        border: 'border-red-200',
        icon: 'text-red-600',
        title: 'text-red-800',
        body: 'text-red-700',
        label: 'Error',
      };
    case 'warning':
      return {
        bg: 'bg-amber-100',
        border: 'border-amber-200',
        icon: 'text-amber-600',
        title: 'text-amber-800',
        body: 'text-amber-700',
        label: 'Warning',
      };
    default:
      return {
        bg: 'bg-blue-100',
        border: 'border-blue-200',
        icon: 'text-blue-600',
        title: 'text-blue-800',
        body: 'text-blue-700',
        label: 'Info',
      };
  }
});
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-0.75rem);
}

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active {
    transition: opacity 1ms linear;
  }

  .toast-enter-from,
  .toast-leave-to {
    transform: none;
  }
}
</style>
