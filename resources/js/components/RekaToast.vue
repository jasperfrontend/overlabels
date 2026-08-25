<template>
  <Teleport to="body">
    <!-- Sits just above the help beacon (h-11 at bottom-4 / sm:bottom-6) on the same right inset. -->
    <TransitionGroup
      tag="div"
      name="toast"
      class="pointer-events-none fixed right-4 bottom-20 left-4 z-50 flex flex-col gap-2 sm:right-6 sm:bottom-24 sm:left-auto sm:w-96"
      @after-leave="onAfterLeave"
    >
      <div
        v-for="(item, index) in items"
        :key="item.id"
        class="pointer-events-auto flex w-full items-start gap-3 overflow-hidden rounded-lg border border-l-4 border-border bg-background py-3 pr-2 pl-4 shadow-lg"
        :class="palette(item.type).edge"
        :role="item.type === 'error' ? 'alert' : 'status'"
        :aria-live="item.type === 'error' ? 'assertive' : 'polite'"
        @mouseenter="pause(item)"
        @mouseleave="resume(item)"
      >
        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="palette(item.type).iconBg" aria-hidden="true">
          <component :is="iconFor(item.type)" class="h-3.5 w-3.5" :class="palette(item.type).icon" stroke-width="2.5" />
        </span>

        <div class="min-w-0 flex-1 pt-0.5">
          <p class="text-xs font-semibold tracking-wide uppercase" :class="palette(item.type).label">
            {{ palette(item.type).text }}
          </p>
          <p class="mt-1 text-sm leading-snug break-words text-foreground">
            <span class="sr-only">{{ palette(item.type).text }}: </span>
            {{ item.message }}
          </p>
          <!-- The slot reflects the caller's current state, so it belongs to the newest toast only. -->
          <slot v-if="index === items.length - 1" />
        </div>

        <button
          type="button"
          @click="remove(item)"
          class="-mt-1 shrink-0 cursor-pointer rounded p-1.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none"
          aria-label="Dismiss notification"
        >
          <X class="h-4 w-4" aria-hidden="true" />
        </button>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script lang="ts" setup>
import { AlertTriangle, Check, Info, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
  message: { type: String, required: true },
  type: { type: String, default: 'info' }, // info | success | warning | error
  duration: { type: Number, default: 3000 },
});

const emit = defineEmits<{ dismiss: [] }>();

type Item = {
  id: number;
  message: string;
  type: string;
  timer: ReturnType<typeof setTimeout> | null;
};

// Callers own a single `message` string. Every change to it becomes its own toast here, with its
// own timer, so a second message while the first is still up stacks instead of overwriting it.
// `dismiss` fires once the LAST toast has left, so `v-if="toastMessage"` on the caller keeps working.
const items = ref<Item[]>([]);
let nextId = 0;

const remove = (item: Item) => {
  pause(item);
  items.value = items.value.filter((i) => i.id !== item.id);
};

const pause = (item: Item) => {
  if (item.timer) clearTimeout(item.timer);
  item.timer = null;
};

const resume = (item: Item) => {
  pause(item);
  if (props.duration <= 0) return; // sticky when duration is 0 or negative
  item.timer = setTimeout(() => remove(item), props.duration);
};

const onAfterLeave = () => {
  if (items.value.length === 0) emit('dismiss');
};

onBeforeUnmount(() => items.value.forEach(pause));

watch(
  () => props.message,
  (message) => {
    if (!message) return;
    const item: Item = { id: nextId++, message, type: props.type, timer: null };
    items.value.push(item);
    resume(item);
  },
  { immediate: true },
);

const iconFor = (type: string) => {
  switch (type) {
    case 'success':
      return Check;
    case 'warning':
      return AlertTriangle;
    case 'error':
      return X;
    default:
      return Info;
  }
};

// Surface is the theme's own background + border, so the toast reads as part of the app in every
// theme. Identity comes from the left edge, the icon disc and the label - never a tinted fill.
const palette = (type: string) => {
  switch (type) {
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
};
</script>

<style scoped>
/* Slide in from the right edge so the motion pulls the eye to the corner; leave by fading only.
   A leaving toast goes absolute so the ones still stacked slide into its place (toast-move). */
.toast-enter-active,
.toast-move {
  transition:
    opacity 0.3s ease-out,
    transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-leave-active {
  position: absolute;
  right: 0;
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
  .toast-leave-active,
  .toast-move {
    transition: opacity 1ms linear;
  }

  .toast-enter-from {
    transform: none;
  }
}
</style>
