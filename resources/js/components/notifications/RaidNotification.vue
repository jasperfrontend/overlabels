<template>
  <NotificationBase
    :visible="visible"
    v-bind="baseProps"
    :custom-class="customClass"
  >
    <div class="raid-notification">
      <div v-if="showIcon" class="raid-icon">
        <component :is="iconComponent" v-if="iconComponent" />
        <span v-else class="default-icon">⚔️</span>
      </div>

      <div class="raid-body">
        <h2 class="raid-title" :style="{ color: titleColor }">
          {{ title }}
        </h2>

        <div class="raid-message" :style="{ color: messageColor }">
          <strong>{{ fromBroadcasterName }}</strong> is raiding with
          <strong class="viewer-count">{{ viewerCount }}</strong> {{ viewerLabel }}!
        </div>

        <div v-if="showWelcomeMessage" class="welcome-message">
          {{ welcomeMessage }}
        </div>
      </div>

      <div v-if="showAnimation" class="raid-animation">
        <slot name="animation">
          <div class="raid-effect"></div>
        </slot>
      </div>
    </div>
  </NotificationBase>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import NotificationBase, { type NotificationBaseProps } from './NotificationBase.vue';
import type { NormalizedEvent } from '@/types';

export interface RaidNotificationProps extends NotificationBaseProps {
  event: NormalizedEvent;
  showIcon?: boolean;
  showWelcomeMessage?: boolean;
  showAnimation?: boolean;
  titleColor?: string;
  messageColor?: string;
  iconComponent?: any;
  welcomeMessage?: string;
}

const props = withDefaults(defineProps<RaidNotificationProps>(), {
  showIcon: true,
  showWelcomeMessage: true,
  showAnimation: true,
  titleColor: '#ff0000',
  messageColor: '#ffffff',
  welcomeMessage: 'Welcome raiders! Thank you for the support!',
});

const baseProps = computed(() => {
  const { event, showIcon, showWelcomeMessage, showAnimation, titleColor, messageColor, iconComponent, welcomeMessage, ...rest } = props;
  return rest;
});

// Suppress unused variable warnings
void (props.event && props.showIcon && props.showWelcomeMessage && props.showAnimation && props.titleColor && props.messageColor && props.iconComponent && props.welcomeMessage);

const fromBroadcasterName = computed(() =>
  props.event.raw?.event?.from_broadcaster_user_name || 'A streamer'
);

const viewerCount = computed(() =>
  props.event.raw?.event?.viewers || 0
);

const viewerLabel = computed(() =>
  viewerCount.value === 1 ? 'viewer' : 'viewers'
);

const title = computed(() => {
  if (viewerCount.value >= 100) {
    return '🔥 MASSIVE RAID INCOMING! 🔥';
  }
  if (viewerCount.value >= 50) {
    return '⚡ RAID INCOMING! ⚡';
  }
  return 'RAID INCOMING!';
});

const customClass = computed(() => {
  const classes = ['raid-notification-wrapper'];
  if (viewerCount.value >= 100) classes.push('massive-raid');
  if (viewerCount.value >= 50) classes.push('large-raid');
  return classes.join(' ');
});
</script>

<style scoped>
.raid-notification {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  min-height: 100px;
  position: relative;
}

.raid-icon {
  flex-shrink: 0;
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  animation: bounce 1s ease-in-out infinite;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.raid-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.raid-title {
  margin: 0;
  font-size: 1.75rem;
  font-weight: bold;
  text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.7);
  animation: glow 2s ease-in-out infinite;
}

@keyframes glow {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.8; }
}

.raid-message {
  font-size: 1.25rem;
  line-height: 1.5;
}

.viewer-count {
  color: #ff0000;
  font-size: 1.5rem;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.welcome-message {
  padding: 0.75rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  font-size: 1rem;
  font-style: italic;
}

.raid-animation {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  overflow: hidden;
}

.raid-effect {
  position: absolute;
  width: 200%;
  height: 200%;
  top: -50%;
  left: -50%;
  background: radial-gradient(circle, rgba(255, 0, 0, 0.3) 0%, transparent 70%);
  animation: pulse-expand 3s ease-out infinite;
}

@keyframes pulse-expand {
  0% {
    transform: scale(0.5);
    opacity: 1;
  }
  100% {
    transform: scale(2);
    opacity: 0;
  }
}

/* Special styles for massive raids */
.massive-raid {
  border-width: 3px !important;
  background: linear-gradient(135deg, rgba(255, 0, 0, 0.2), rgba(255, 107, 0, 0.2)) !important;
}

.massive-raid .raid-title {
  font-size: 2rem;
  background: linear-gradient(90deg, #ff0000, #ff6b00, #ff0000);
  background-size: 200% 100%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gradient-shift 3s linear infinite;
}

@keyframes gradient-shift {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}

.large-raid .raid-title {
  color: #ff6b00;
}
</style>
