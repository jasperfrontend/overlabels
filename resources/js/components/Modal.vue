<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';

interface ModalProps {
  show: boolean;
  maxWidth?: string;
  closeable?: boolean;
}

const props = defineProps<ModalProps>();

// Set default values for optional props
const closeable = props.closeable;

const emit = defineEmits(['close']);

watch(() => props.show, (newValue) => {
  if (newValue) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

const close = () => {
  if (props.closeable) {
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
  return {
    'sm': 'sm:max-w-sm',
    'md': 'sm:max-w-md',
    'lg': 'sm:max-w-lg',
    'xl': 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
    '6xl': 'sm:max-w-6xl',
  }[props.maxWidth];
});
</script>

<template>
  <teleport to="body">
    <!-- Background overlay -->
    <div v-if="props.show" class="fixed inset-0 bg-black/50 backdrop-blur bg-opacity-50 z-40"></div>

    <transition leave-active-class="duration-200">
      <div v-show="props.show" class="fixed inset-0 max-w-[600px] m-auto overflow-y-auto px-4 py-6 sm:px-0 z-50 flex items-center justify-center">

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
            class="mb-6 bg-background border border-border rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:mx-auto dark:bg-background dark:border-border"
            :class="maxWidthClass"
          >
            <div v-show="props.show" class="relative">
              <!-- Close button -->
              <button
                v-if="closeable"
                @click="close"
                class="absolute right-4 top-4 cursor-pointer w-6 h-6 hover:bg-sidebar text-xl font-bold focus:rotate-180 transition-transform rounded-full flex items-center justify-center"
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
