<template>
  <NotificationBase
    :visible="visible"
    v-bind="baseProps"
    :custom-class="customClass"
  >
    <div class="generic-notification">
      <div v-if="showIcon" class="notification-icon">
        <component :is="iconComponent" v-if="iconComponent" />
        <span v-else class="default-icon">{{ icon }}</span>
      </div>
      
      <div class="notification-body">
        <h3 v-if="title" class="notification-title" :style="{ color: titleColor }">
          {{ title }}
        </h3>
        
        <div v-if="message" class="notification-message" :style="{ color: messageColor }">
          {{ message }}
        </div>
        
        <div v-if="subMessage" class="notification-submessage" :style="{ color: subMessageColor }">
          {{ subMessage }}
        </div>
        
        <div v-if="metadata && Object.keys(metadata).length > 0" class="notification-metadata">
          <span v-for="(value, key) in metadata" :key="key" class="metadata-item">
            {{ key }}: {{ value }}
          </span>
        </div>
      </div>
    </div>
  </NotificationBase>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import NotificationBase, { type NotificationBaseProps } from './NotificationBase.vue';
import type { NormalizedEvent } from '@/types';

export interface GenericNotificationProps extends NotificationBaseProps {
  event: NormalizedEvent;
  showIcon?: boolean;
  icon?: string;
  title?: string;
  message?: string;
  subMessage?: string;
  metadata?: Record<string, any>;
  titleColor?: string;
  messageColor?: string;
  subMessageColor?: string;
  iconComponent?: any;
}

const props = withDefaults(defineProps<GenericNotificationProps>(), {
  showIcon: true,
  icon: '📢',
  titleColor: '#ffffff',
  messageColor: '#ffffff',
  subMessageColor: '#cccccc',
});

const baseProps = computed(() => {
  const { 
    event, 
    showIcon, 
    icon, 
    title, 
    message, 
    subMessage, 
    metadata, 
    titleColor, 
    messageColor, 
    subMessageColor, 
    iconComponent, 
    ...rest 
  } = props;
  return rest;
});

// Suppress unused variable warnings
void (props.event && props.showIcon && props.icon && props.title && props.message && props.subMessage && props.metadata && props.titleColor && props.messageColor && props.subMessageColor && props.iconComponent);

const customClass = computed(() => `generic-notification-wrapper event-${props.event.type.replace(/\./g, '-')}`);
</script>

<style scoped>
.generic-notification {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 60px;
}

.notification-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.notification-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.notification-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.notification-message {
  font-size: 1rem;
  line-height: 1.4;
}

.notification-submessage {
  font-size: 0.875rem;
  opacity: 0.9;
}

.notification-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.25rem;
}

.metadata-item {
  padding: 0.25rem 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  font-size: 0.75rem;
  opacity: 0.8;
}
</style>