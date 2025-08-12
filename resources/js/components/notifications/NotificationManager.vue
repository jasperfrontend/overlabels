<template>
  <div class="notification-manager">
    <component
      :is="currentComponent"
      v-if="currentNotification"
      :key="currentNotification.id"
      :visible="true"
      :event="currentNotification.event"
      :count="currentNotification.count"
      v-bind="mergedProps"
    />

    <div v-if="showQueue && queue.length > 0" class="notification-queue-indicator">
      <span>{{ queue.length }} notifications queued</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted } from 'vue';
import { useNotificationQueue, type NotificationQueueConfig } from '@/composables/useNotificationQueue';
import StableSubscriptionNotification from './StableSubscriptionNotification.vue';
import RaidNotification from './RaidNotification.vue';
import FollowNotification from './FollowNotification.vue';
import CheerNotification from './CheerNotification.vue';
import type { NormalizedEvent } from '@/types';

export interface NotificationManagerProps {
  queueConfig?: NotificationQueueConfig;
  showQueue?: boolean;
  defaultProps?: Record<string, any>;
  componentMap?: Record<string, any>;
  propsMap?: Record<string, Record<string, any>>;
}

const props = withDefaults(defineProps<NotificationManagerProps>(), {
  showQueue: false,
  defaultProps: () => ({
    position: 'top-center',
    size: 'medium',
    transitionName: 'notification-slide',
    backgroundColor: 'rgba(0, 0, 0, 0.9)',
    borderColor: '#ff6b00',
    borderWidth: 2,
    borderRadius: 8,
    padding: 16,
    margin: 16,
    fontFamily: 'system-ui, -apple-system, sans-serif',
    fontSize: 16,
    fontColor: '#ffffff',
  }),
  componentMap: () => ({
    'channel.subscribe': StableSubscriptionNotification,
    'channel.subscription.gift': StableSubscriptionNotification,
    'channel.subscription.message': StableSubscriptionNotification,
    'channel.raid': RaidNotification,
    'channel.follow': FollowNotification,
    'channel.cheer': CheerNotification,
  }),
  propsMap: () => ({}),
});

const emit = defineEmits<{
  notificationShown: [notification: NormalizedEvent];
  notificationHidden: [notification: NormalizedEvent];
  queueUpdated: [size: number];
}>();

const notificationQueue = useNotificationQueue(props.queueConfig);
const { currentNotification, queue, addToQueue } = notificationQueue;

const currentComponent = computed(() => {
  if (!currentNotification.value) return null;
  const eventType = currentNotification.value.event.type;

  // Only show notifications for explicitly mapped event types
  if (!props.componentMap[eventType]) {
    return null;
  }

  return props.componentMap[eventType];
});

const mergedProps = computed(() => {
  if (!currentNotification.value) return props.defaultProps;
  const eventType = currentNotification.value.event.type;
  const eventSpecificProps = props.propsMap[eventType] || {};
  return {
    ...props.defaultProps,
    ...eventSpecificProps,
  };
});

watch(currentNotification, (newVal, oldVal) => {
  if (newVal && !oldVal) {
    emit('notificationShown', newVal.event);
  } else if (!newVal && oldVal) {
    emit('notificationHidden', oldVal.event);
  }
});

watch(queue, () => {
  emit('queueUpdated', queue.value.length);
}, { deep: true });

const handleEvent = (event: NormalizedEvent) => {
  addToQueue(event);
};

onMounted(() => {
  window.addEventListener('twitch-event-normalized', ((e: CustomEvent) => {
    handleEvent(e.detail);
  }) as EventListener);
});

onUnmounted(() => {
  window.removeEventListener('twitch-event-normalized', ((e: CustomEvent) => {
    handleEvent(e.detail);
  }) as EventListener);
});

defineExpose({
  addToQueue,
  clearQueue: notificationQueue.clearQueue,
  skipCurrent: notificationQueue.skipCurrent,
  queue,
  currentNotification,
});
</script>

<style scoped>
.notification-manager {
  position: relative;
  z-index: 1000;
}

.notification-queue-indicator {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 8px 16px;
  background: rgba(0, 0, 0, 0.8);
  border: 1px solid #ff6b00;
  border-radius: 20px;
  color: #ffffff;
  font-size: 0.875rem;
  font-family: system-ui, -apple-system, sans-serif;
  z-index: 999;
}
</style>
