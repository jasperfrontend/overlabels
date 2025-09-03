import { ref, computed, watch } from 'vue';
import type { NormalizedEvent } from '@/types';

export interface QueuedNotification {
  id: string;
  event: NormalizedEvent;
  priority: number;
  addedAt: number;
  displayDuration: number;
  groupKey?: string;
  count?: number;
}

export interface NotificationQueueConfig {
  maxQueueSize?: number;
  defaultDisplayDuration?: number;
  groupingWindow?: number;
  maxGroupSize?: number;
}

const DEFAULT_CONFIG: Required<NotificationQueueConfig> = {
  maxQueueSize: 100,
  defaultDisplayDuration: 5000,
  groupingWindow: 3000,
  maxGroupSize: 50,
};

export function useNotificationQueue(config: NotificationQueueConfig = {}) {
  const mergedConfig = { ...DEFAULT_CONFIG, ...config };

  const queue = ref<QueuedNotification[]>([]);
  const currentNotification = ref<QueuedNotification | null>(null);
  const isDisplaying = ref(false);
  let displayTimeout: number | null = null;

  const queueSize = computed(() => queue.value.length);

  const getEventPriority = (event: NormalizedEvent): number => {
    const priorityMap: Record<string, number> = {
      'channel.raid': 10,
      'channel.subscription.gift': 9,
      'channel.subscribe': 8,
      'channel.cheer': 7,
      'channel.follow': 5,
      'channel.point_reward.redeem': 4,
      'channel.poll.begin': 3,
      'channel.prediction.begin': 3,
    };
    return priorityMap[event.type] || 1;
  };

  const getGroupKey = (event: NormalizedEvent): string | undefined => {
    // Only group regular follows and cheers, not gift subscriptions
    // (gift bombs are handled upstream by useGiftBombDetector)
    const groupableTypes = [
      'channel.follow',
      'channel.cheer',
    ];

    if (!groupableTypes.includes(event.type)) {
      return undefined;
    }

    return `${event.type}-group`;
  };

  const getDisplayDuration = (notification: QueuedNotification): number => {
    const baseDuration = mergedConfig.defaultDisplayDuration;

    // For gift bombs, scale duration based on the number of gifts
    if (notification.event.type === 'channel.subscription.gift' && notification.event.gift_count) {
      const giftCount = notification.event.gift_count;
      if (giftCount >= 50) return 10000; // 10 seconds for 50+ gifts
      if (giftCount >= 20) return 8000;  // 8 seconds for 20+ gifts
      if (giftCount >= 5) return 6000;   // 6 seconds for 5+ gifts
      return 5000; // 5 seconds for smaller gift bombs
    }

    // Standard grouping duration increase
    if (notification.count && notification.count > 1) {
      return baseDuration + (notification.count * 200);
    }

    const durationMap: Record<string, number> = {
      'channel.raid': 8000,
      'channel.subscription.gift': 6000,
      'channel.subscribe': 5000,
      'channel.cheer': 4000,
      'channel.follow': 3000,
    };

    return durationMap[notification.event.type] || baseDuration;
  };

  const addToQueue = (event: NormalizedEvent) => {
    const now = Date.now();
    const isLiveUpdate = event.raw?.event?.is_live_update;
    const isFinal = event.raw?.event?.is_final;

    // Define supported event types - only these will show notifications
    const supportedEventTypes = [
      'channel.subscribe',
      'channel.subscription.gift',
      'channel.subscription.message',
      'channel.raid',
      'channel.follow',
      'channel.cheer',
    ];

    // Skip unknown/unsupported event types
    if (!supportedEventTypes.includes(event.type)) {
      return;
    }

    // Handle live gift bomb updates and finals
    if ((isLiveUpdate || isFinal) && event.type === 'channel.subscription.gift') {
      // Check if this notification is already in queue
      const existingInQueue = queue.value.find(n => n.id === event.id);
      if (existingInQueue) {
        // Update the existing event object to maintain component stability
        Object.assign(existingInQueue.event, {
          gift_count: event.gift_count,
          raw: {
            ...existingInQueue.event.raw,
            event: {
              ...existingInQueue.event.raw?.event,
              total: event.gift_count,
              is_live_update: isLiveUpdate,
              is_final: isFinal,
            }
          }
        });
        existingInQueue.count = event.gift_count;
        existingInQueue.displayDuration = getDisplayDuration(existingInQueue);
        return;
      }

      // Check if this notification is currently being displayed
      if (currentNotification.value && currentNotification.value.id === event.id) {
        // Update the existing event object properties to maintain component stability
        Object.assign(currentNotification.value.event, {
          gift_count: event.gift_count,
          raw: {
            ...currentNotification.value.event.raw,
            event: {
              ...currentNotification.value.event.raw?.event,
              total: event.gift_count,
              is_live_update: isLiveUpdate,
              is_final: isFinal,
            }
          }
        });
        currentNotification.value.count = event.gift_count;

        // If this is the final update, don't extend the timeout - let it finish naturally
        if (!isFinal) {
          // Extend display time for live updates
          if (displayTimeout) {
            clearTimeout(displayTimeout);
            displayTimeout = window.setTimeout(() => {
              currentNotification.value = null;
              isDisplaying.value = false;
              displayTimeout = null;
              processNextNotification();
            }, getDisplayDuration(currentNotification.value));
          }
        }
        return;
      }
    }

    const groupKey = getGroupKey(event);

    if (groupKey && !isLiveUpdate) {
      const existingGroup = queue.value.find(
        n => n.groupKey === groupKey &&
        (now - n.addedAt) < mergedConfig.groupingWindow
      );

      if (existingGroup && (existingGroup.count || 0) < mergedConfig.maxGroupSize) {
        existingGroup.count = (existingGroup.count || 1) + 1;
        existingGroup.displayDuration = getDisplayDuration(existingGroup);
        return;
      }
    }

    const notification: QueuedNotification = {
      id: event.id,
      event,
      priority: getEventPriority(event),
      addedAt: now,
      displayDuration: mergedConfig.defaultDisplayDuration,
      groupKey,
      count: event.gift_count || 1,
    };

    notification.displayDuration = getDisplayDuration(notification);

    queue.value.push(notification);
    queue.value.sort((a, b) => b.priority - a.priority || a.addedAt - b.addedAt);

    if (queue.value.length > mergedConfig.maxQueueSize) {
      queue.value = queue.value.slice(0, mergedConfig.maxQueueSize);
    }
  };

  const processNextNotification = () => {
    if (isDisplaying.value || queue.value.length === 0) {
      return;
    }

    const next = queue.value.shift();
    if (!next) return;

    currentNotification.value = next;
    isDisplaying.value = true;

    if (displayTimeout) {
      clearTimeout(displayTimeout);
    }

    displayTimeout = window.setTimeout(() => {
      currentNotification.value = null;
      isDisplaying.value = false;
      displayTimeout = null;
      processNextNotification();
    }, next.displayDuration);
  };

  const clearQueue = () => {
    queue.value = [];
    if (displayTimeout) {
      clearTimeout(displayTimeout);
      displayTimeout = null;
    }
    currentNotification.value = null;
    isDisplaying.value = false;
  };

  const skipCurrent = () => {
    if (displayTimeout) {
      clearTimeout(displayTimeout);
      displayTimeout = null;
    }
    currentNotification.value = null;
    isDisplaying.value = false;
    processNextNotification();
  };

  watch(queue, () => {
    if (!isDisplaying.value && queue.value.length > 0) {
      processNextNotification();
    }
  }, { deep: true });

  return {
    queue: computed(() => queue.value),
    currentNotification: computed(() => currentNotification.value),
    isDisplaying: computed(() => isDisplaying.value),
    queueSize,
    addToQueue,
    clearQueue,
    skipCurrent,
    processNextNotification,
  };
}
