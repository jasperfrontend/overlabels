<template>
  <NotificationBase
    :visible="visible"
    v-bind="baseProps"
    :custom-class="customClass"
  >
    <div class="cheer-notification">
      <div v-if="showIcon" class="cheer-icon">
        <component :is="iconComponent" v-if="iconComponent" />
        <span v-else class="default-icon">💎</span>
      </div>
      
      <div class="cheer-body">
        <h3 class="cheer-title" :style="{ color: titleColor }">
          {{ title }}
        </h3>
        
        <div class="cheer-amount" :style="{ color: amountColor }">
          {{ bits }} {{ bitsLabel }}
        </div>
        
        <div v-if="showMessage && message" class="cheer-message" :style="{ color: messageColor }">
          "{{ message }}"
        </div>
      </div>
      
      <div v-if="showAnimation" class="cheer-animation">
        <slot name="animation">
          <div class="bits-rain"></div>
        </slot>
      </div>
    </div>
  </NotificationBase>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import NotificationBase, { type NotificationBaseProps } from './NotificationBase.vue';
import type { NormalizedEvent } from '@/types';

export interface CheerNotificationProps extends NotificationBaseProps {
  event: NormalizedEvent;
  showIcon?: boolean;
  showMessage?: boolean;
  showAnimation?: boolean;
  titleColor?: string;
  messageColor?: string;
  amountColor?: string;
  iconComponent?: any;
}

const props = withDefaults(defineProps<CheerNotificationProps>(), {
  showIcon: true,
  showMessage: true,
  showAnimation: true,
  titleColor: '#9146ff',
  messageColor: '#ffffff',
  amountColor: '#00d4ff',
});

const baseProps = computed(() => {
  const { event, showIcon, showMessage, showAnimation, titleColor, messageColor, amountColor, iconComponent, ...rest } = props;
  return rest;
});

// Suppress unused variable warnings
void (props.event && props.showIcon && props.showMessage && props.showAnimation && props.titleColor && props.messageColor && props.amountColor && props.iconComponent);

const userName = computed(() => props.event.user_name || 'Anonymous');
const bits = computed(() => props.event.raw?.event?.bits || 0);
const message = computed(() => props.event.raw?.event?.message || '');

const bitsLabel = computed(() => bits.value === 1 ? 'bit' : 'bits');

const title = computed(() => {
  if (bits.value >= 10000) {
    return `💎 ${userName.value} with the MEGA CHEER! 💎`;
  }
  if (bits.value >= 5000) {
    return `⭐ ${userName.value} cheered big! ⭐`;
  }
  if (bits.value >= 1000) {
    return `${userName.value} cheered!`;
  }
  return `${userName.value} cheered!`;
});

const customClass = computed(() => {
  const classes = ['cheer-notification-wrapper'];
  if (bits.value >= 10000) classes.push('mega-cheer');
  if (bits.value >= 5000) classes.push('big-cheer');
  if (bits.value >= 1000) classes.push('large-cheer');
  return classes.join(' ');
});
</script>

<style scoped>
.cheer-notification {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 80px;
  position: relative;
}

.cheer-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  animation: sparkle 2s ease-in-out infinite;
}

@keyframes sparkle {
  0%, 100% { transform: scale(1) rotate(0deg); }
  25% { transform: scale(1.1) rotate(5deg); }
  75% { transform: scale(0.95) rotate(-5deg); }
}

.cheer-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.cheer-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: bold;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.cheer-amount {
  font-size: 1.5rem;
  font-weight: bold;
  text-shadow: 0 0 10px currentColor;
  animation: pulse-glow 2s ease-in-out infinite;
}

@keyframes pulse-glow {
  0%, 100% { opacity: 1; text-shadow: 0 0 10px currentColor; }
  50% { opacity: 0.9; text-shadow: 0 0 20px currentColor; }
}

.cheer-message {
  padding: 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  font-style: italic;
  font-size: 1rem;
}

.cheer-animation {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  overflow: hidden;
}

.bits-rain {
  position: absolute;
  width: 100%;
  height: 100%;
  background-image: 
    radial-gradient(circle at 20% 50%, rgba(0, 212, 255, 0.3) 0%, transparent 50%),
    radial-gradient(circle at 80% 50%, rgba(145, 70, 255, 0.3) 0%, transparent 50%);
  animation: float-up 3s ease-out infinite;
}

@keyframes float-up {
  0% {
    transform: translateY(100%);
    opacity: 0;
  }
  20% {
    opacity: 1;
  }
  80% {
    opacity: 1;
  }
  100% {
    transform: translateY(-100%);
    opacity: 0;
  }
}

/* Special styles for big cheers */
.mega-cheer {
  border-width: 3px !important;
  background: linear-gradient(135deg, rgba(0, 212, 255, 0.2), rgba(145, 70, 255, 0.2)) !important;
  animation: rainbow-border 3s linear infinite;
}

@keyframes rainbow-border {
  0%, 100% { border-color: #00d4ff; }
  33% { border-color: #9146ff; }
  66% { border-color: #ff6b00; }
}

.big-cheer .cheer-amount {
  font-size: 2rem;
}

.large-cheer .cheer-amount {
  font-size: 1.75rem;
}
</style>