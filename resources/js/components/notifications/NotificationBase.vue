<template>
  <transition
    :name="transitionName"
    @enter="onEnter"
    @leave="onLeave"
  >
    <div
      v-if="visible"
      class="notification-container"
      :class="[
        positionClass,
        sizeClass,
        customClass
      ]"
      :style="computedStyles"
    >
      <div 
        v-if="backgroundImage" 
        class="notification-background"
        :style="{ backgroundImage: `url(${backgroundImage})` }"
      />
      
      <div class="notification-content">
        <slot />
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { computed, CSSProperties } from 'vue';

export interface NotificationBaseProps {
  visible?: boolean;
  position?: 'top-left' | 'top-center' | 'top-right' | 'middle-left' | 'middle-center' | 'middle-right' | 'bottom-left' | 'bottom-center' | 'bottom-right';
  size?: 'small' | 'medium' | 'large' | 'xl';
  transitionName?: string;
  backgroundColor?: string;
  backgroundImage?: string;
  borderColor?: string;
  borderWidth?: number;
  borderRadius?: number;
  padding?: number;
  margin?: number;
  opacity?: number;
  fontFamily?: string;
  fontSize?: number;
  fontColor?: string;
  shadowSize?: number;
  shadowColor?: string;
  customClass?: string;
  customStyles?: CSSProperties;
  onEnter?: () => void;
  onLeave?: () => void;
}

const props = withDefaults(defineProps<NotificationBaseProps>(), {
  visible: false,
  position: 'top-center',
  size: 'medium',
  transitionName: 'notification-slide',
  backgroundColor: 'rgba(0, 0, 0, 0.9)',
  borderColor: '#ff6b00',
  borderWidth: 2,
  borderRadius: 8,
  padding: 16,
  margin: 16,
  opacity: 1,
  fontFamily: 'system-ui, -apple-system, sans-serif',
  fontSize: 16,
  fontColor: '#ffffff',
  shadowSize: 10,
  shadowColor: 'rgba(0, 0, 0, 0.5)',
});

const positionClass = computed(() => `position-${props.position}`);
const sizeClass = computed(() => `size-${props.size}`);

const computedStyles = computed(() => {
  const styles: CSSProperties = {
    backgroundColor: props.backgroundColor,
    borderColor: props.borderColor,
    borderWidth: `${props.borderWidth}px`,
    borderStyle: 'solid',
    borderRadius: `${props.borderRadius}px`,
    padding: `${props.padding}px`,
    margin: `${props.margin}px`,
    opacity: props.opacity,
    fontFamily: props.fontFamily,
    fontSize: `${props.fontSize}px`,
    color: props.fontColor,
    boxShadow: `0 0 ${props.shadowSize}px ${props.shadowColor}`,
    ...props.customStyles,
  };
  
  return styles;
});
</script>

<style scoped>
.notification-container {
  position: fixed;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.notification-background {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-size: cover;
  background-position: center;
  opacity: 0.3;
  z-index: -1;
}

.notification-content {
  position: relative;
  z-index: 1;
}

/* Position classes */
.position-top-left { top: 0; left: 0; }
.position-top-center { top: 0; left: 50%; transform: translateX(-50%); }
.position-top-right { top: 0; right: 0; }
.position-middle-left { top: 50%; left: 0; transform: translateY(-50%); }
.position-middle-center { top: 50%; left: 50%; transform: translate(-50%, -50%); }
.position-middle-right { top: 50%; right: 0; transform: translateY(-50%); }
.position-bottom-left { bottom: 0; left: 0; }
.position-bottom-center { bottom: 0; left: 50%; transform: translateX(-50%); }
.position-bottom-right { bottom: 0; right: 0; }

/* Size classes */
.size-small { min-width: 200px; max-width: 300px; }
.size-medium { min-width: 300px; max-width: 500px; }
.size-large { min-width: 400px; max-width: 700px; }
.size-xl { min-width: 500px; max-width: 900px; }

/* Transitions */
.notification-slide-enter-active,
.notification-slide-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-slide-enter-from {
  transform: translateY(-100%);
  opacity: 0;
}

.notification-slide-leave-to {
  transform: translateY(100%);
  opacity: 0;
}

.notification-fade-enter-active,
.notification-fade-leave-active {
  transition: opacity 0.5s ease;
}

.notification-fade-enter-from,
.notification-fade-leave-to {
  opacity: 0;
}

.notification-scale-enter-active,
.notification-scale-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.notification-scale-enter-from {
  transform: scale(0);
  opacity: 0;
}

.notification-scale-leave-to {
  transform: scale(0);
  opacity: 0;
}
</style>