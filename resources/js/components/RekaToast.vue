<template>
  <Teleport to="body">
    <Transition name="toast" @after-leave="emit('dismiss')">
      <div
        v-if="visible"
        class="pointer-events-auto fixed right-4 bottom-20 left-4 z-50 flex items-start gap-3 overflow-hidden rounded-lg border border-l-4 border-border bg-background py-3 pr-2 pl-4 shadow-lg sm:right-6 sm:bottom-24 sm:left-auto sm:w-96"
        :class="color.edge"
        :role="toastRole"
        :aria-live="ariaLive"
        @mouseenter="pauseTimeout"
        @mouseleave="resumeTimeout"
      >
        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="color.iconBg" aria-hidden="true">
          <component :is="icon" class="h-3.5 w-3.5" :class="color.icon" stroke-width="2.5" />
        </span>

        <div class="min-w-0 flex-1 pt-0.5">
          <p class="text-xs font-semibold tracking-wide uppercase" :class="color.label">
            {{ color.text }}
          </p>
          <p class="mt-1 text-sm leading-snug break-words text-foreground">
            <span class="sr-only">{{ color.text }}: </span>
            {{ message }}
          </p>
          <slot />
        </div>

        <button
          type="button"
          @click="dismiss"
          class="-mt-1 shrink-0 cursor-pointer rounded p-1.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none"
          aria-label="Dismiss notification"
        >
          <X class="h-4 w-4" aria-hidden="true" />
        </button>
      </div>
    </Transition>
  </Teleport>
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

// Surface is the theme's own background + border, so the toast reads as part of the app in every
// theme. Identity comes from the left edge, the icon disc and the label - never a tinted fill.
const color = computed(() => {
  switch (props.type) {
    case 'success':
      return {
        edge: 'border-l-violet-500',
        iconBg: 'bg-violet-500/15',
        icon: 'text-violet-600 dark:text-violet-400',
        label: 'text-violet-600 dark:text-violet-400',
        text: 'Success',
      };
    case 'error':
      return {
        edge: 'border-l-red-500',
        iconBg: 'bg-red-500/15',
        icon: 'text-red-600 dark:text-red-400',
        label: 'text-red-600 dark:text-red-400',
        text: 'Error',
      };
    case 'warning':
      return {
        edge: 'border-l-amber-500',
        iconBg: 'bg-amber-500/15',
        icon: 'text-amber-600 dark:text-amber-400',
        label: 'text-amber-600 dark:text-amber-400',
        text: 'Warning',
      };
    default:
      return {
        edge: 'border-l-sky-500',
        iconBg: 'bg-sky-500/15',
        icon: 'text-sky-600 dark:text-sky-400',
        label: 'text-sky-600 dark:text-sky-400',
        text: 'Info',
      };
  }
});
</script>

<style scoped>
/* Slide in from the right edge so the motion pulls the eye to the corner; leave by fading only. */
.toast-enter-active {
  transition:
    opacity 0.3s ease-out,
    transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-leave-active {
  transition: opacity 0.35s ease-in;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(calc(100% + 1.5rem));
}

.toast-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active {
    transition: opacity 1ms linear;
  }

  .toast-enter-from {
    transform: none;
  }
}
</style>
