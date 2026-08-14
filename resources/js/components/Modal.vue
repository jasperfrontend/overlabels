<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';

interface ModalProps {
  show: boolean;
  maxWidth?: string;
  closeable?: boolean;
}

const props = defineProps<ModalProps>();

// Set default values for optional props
const closeable = props.closeable ?? true;

const emit = defineEmits(['close']);

watch(
  () => props.show,
  (newValue) => {
    if (newValue) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  },
);

const close = () => {
  if (closeable) {
    emit('close');
  }
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.show) {
    close();
  }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);
  document.body.style.overflow = '';
});

const maxWidthClass = computed(() => {
  return (
    {
      sm: 'sm:max-w-sm',
      md: 'sm:max-w-md',
      lg: 'sm:max-w-lg',
      xl: 'sm:max-w-xl',
      '2xl': 'sm:max-w-2xl',
      '3xl': 'sm:max-w-3xl',
      '4xl': 'sm:max-w-4xl',
      '5xl': 'sm:max-w-5xl',
      '6xl': 'sm:max-w-6xl',
    }[props.maxWidth || '2xl'] || []
  );
});
</script>

<template>
  <teleport to="body">
    <!-- Background overlay -->
    <div v-if="props.show" class="bg-opacity-50 fixed inset-0 z-40 bg-black/50 backdrop-blur"></div>

    <transition leave-active-class="duration-200">
      <div
        v-show="props.show"
        class="fixed inset-0 z-50 m-auto flex max-w-[600px] items-center justify-center overflow-y-auto px-4 py-6 sm:px-0"
        @click.self="close"
      >
        <transition
          enter-active-class="ease-out duration-300"
          enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          enter-to-class="opacity-100 translate-y-0 sm:scale-100"
          leave-active-class="ease-in duration-200"
          leave-from-class="opacity-100 translate-y-0 sm:scale-100"
          leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
          <div
            v-show="props.show"
            class="mb-6 transform overflow-hidden rounded-lg border border-border bg-background shadow-xl transition-all sm:mx-auto sm:w-full dark:border-border dark:bg-background"
            :class="maxWidthClass"
          >
            <div v-show="props.show" class="relative">
              <!-- Close button -->
              <button
                v-if="closeable"
                @click="close"
                class="absolute top-4 right-4 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full text-xl font-bold transition-transform hover:bg-sidebar focus:rotate-180"
                aria-label="Close"
              >
                &times;
              </button>
              <slot />
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </teleport>
</template>
