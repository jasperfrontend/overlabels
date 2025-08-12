<template>
  <NotificationBase
    :visible="visible"
    v-bind="baseProps"
    :custom-class="customClass"
  >
    <div class="follow-notification">
      <div v-if="showIcon" class="follow-icon">
        <component :is="iconComponent" v-if="iconComponent" />
        <span v-else class="default-icon">❤️</span>
      </div>
      
      <div class="follow-body">
        <h3 class="follow-title" :style="{ color: titleColor }">
          {{ title }}
        </h3>
        
        <div v-if="showMessage" class="follow-message" :style="{ color: messageColor }">
          {{ message }}
        </div>
      </div>
    </div>
  </NotificationBase>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import NotificationBase, { type NotificationBaseProps } from './NotificationBase.vue';
import type { NormalizedEvent } from '@/types';

export interface FollowNotificationProps extends NotificationBaseProps {
  event: NormalizedEvent;
  showIcon?: boolean;
  showMessage?: boolean;
  titleColor?: string;
  messageColor?: string;
  iconComponent?: any;
  message?: string;
}

const props = withDefaults(defineProps<FollowNotificationProps>(), {
  showIcon: true,
  showMessage: true,
  titleColor: '#9146ff',
  messageColor: '#ffffff',
  message: 'Welcome to the community!',
});

const baseProps = computed(() => {
  const { event, showIcon, showMessage, titleColor, messageColor, iconComponent, message, ...rest } = props;
  return rest;
});

// Suppress unused variable warnings
void (props.event && props.showIcon && props.showMessage && props.titleColor && props.messageColor && props.iconComponent && props.message);

const userName = computed(() => props.event.user_name || 'Someone');

const title = computed(() => `${userName.value} just followed!`);

const customClass = computed(() => 'follow-notification-wrapper');
</script>

<style scoped>
.follow-notification {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 60px;
}

.follow-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  animation: heartbeat 1.5s ease-in-out infinite;
}

@keyframes heartbeat {
  0%, 100% { transform: scale(1); }
  25% { transform: scale(1.1); }
  45% { transform: scale(0.9); }
}

.follow-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.follow-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.follow-message {
  font-size: 0.875rem;
  opacity: 0.9;
}

.follow-notification-wrapper {
  animation: subtle-glow 3s ease-in-out infinite;
}

@keyframes subtle-glow {
  0%, 100% { box-shadow: 0 0 10px rgba(145, 70, 255, 0.3); }
  50% { box-shadow: 0 0 20px rgba(145, 70, 255, 0.5); }
}
</style>