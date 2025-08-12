<template>
  <NotificationBase
    :visible="visible"
    v-bind="baseProps"
    :custom-class="customClass"
  >
    <div class="subscription-notification">
      <div v-if="showIcon" class="notification-icon">
        <component :is="iconComponent" v-if="iconComponent" />
        <span v-else class="default-icon">⭐</span>
      </div>

      <div class="notification-body">
        <h3 class="notification-title" :style="{ color: titleColor }">
          {{ currentTitle }}
        </h3>

        <div class="notification-message" :style="{ color: messageColor }">
          <template v-if="currentIsGiftBomb">
            <strong>{{ currentGifterName }}</strong> just gifted
            <strong class="gift-count" :class="{ 'count-updating': isAnimating }">{{ currentGiftCount }}</strong>
            {{ currentTierLabel }} subscriptions to the community!
          </template>
          <template v-else-if="currentIsGift">
            <strong>{{ currentUserName }}</strong> received a {{ currentTierLabel }} subscription gift from <strong>{{ currentGifterName }}</strong>!
          </template>
          <template v-else>
            <strong>{{ currentUserName }}</strong> subscribed with {{ currentTierLabel }}!
          </template>

          <div v-if="showMessage && currentMessage" class="user-message">
            "{{ currentMessage }}"
          </div>
        </div>

        <div v-if="showStats" class="notification-stats">
          <span v-if="currentMonthsSubscribed">{{ currentMonthsSubscribed }} months</span>
          <span v-if="currentStreakMonths">{{ currentStreakMonths }} month streak</span>
        </div>
      </div>

      <div v-if="showAnimation" class="notification-animation">
        <slot name="animation" />
      </div>
    </div>
  </NotificationBase>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import NotificationBase, { type NotificationBaseProps } from './NotificationBase.vue';
import type { NormalizedEvent } from '@/types';

export interface StableSubscriptionNotificationProps extends NotificationBaseProps {
  event: NormalizedEvent;
  count?: number;
  showIcon?: boolean;
  showMessage?: boolean;
  showStats?: boolean;
  showAnimation?: boolean;
  titleColor?: string;
  messageColor?: string;
  iconComponent?: any;
  tierLabels?: Record<string, string>;
}

const props = withDefaults(defineProps<StableSubscriptionNotificationProps>(), {
  showIcon: true,
  showMessage: true,
  showStats: true,
  showAnimation: true,
  titleColor: '#ff6b00',
  messageColor: '#ffffff',
  tierLabels: () => ({
    '1000': 'Tier 1',
    '2000': 'Tier 2',
    '3000': 'Tier 3',
    'Prime': 'Prime',
  }),
});

// Internal state to prevent re-renders
const currentUserName = ref('');
const currentGifterName = ref('');
const currentGiftCount = ref(1);
const currentTierLabel = ref('Tier 1');
const currentIsGift = ref(false);
const currentIsGiftBomb = ref(false);
const currentMessage = ref('');
const currentMonthsSubscribed = ref<number | undefined>(undefined);
const currentStreakMonths = ref<number | undefined>(undefined);
const isAnimating = ref(false);

const baseProps = computed(() => {
  const { event: _event, count: _count, showIcon: _showIcon, showMessage: _showMessage, showStats: _showStats, showAnimation: _showAnimation, titleColor: _titleColor, messageColor: _messageColor, iconComponent: _iconComponent, tierLabels: _tierLabels, ...rest } = props;
  return rest;
});

// Suppress unused variable warnings
void (props.event && props.count && props.showIcon && props.showMessage && props.showStats && props.showAnimation && props.titleColor && props.messageColor && props.iconComponent && props.tierLabels);

const currentTitle = computed(() => {
  if (currentIsGiftBomb.value) {
    const count = currentGiftCount.value;
    if (count >= 100) return '🔥💎 MASSIVE GIFT BOMB! 💎🔥';
    if (count >= 50) return '🎆💥 HUGE GIFT BOMB! 💥🎆';
    if (count >= 20) return '🎉🎁 BIG GIFT BOMB! 🎁🎉';
    if (count >= 10) return '🎉 GIFT BOMB! 🎉';
    return '🎁 Gift Bomb! 🎁';
  }
  if (currentIsGift.value) {
    return `${currentUserName.value} got a gift sub!`;
  }
  return `${currentUserName.value} subscribed!`;
});

const customClass = computed(() => {
  const classes = ['subscription-notification-wrapper'];
  if (currentIsGiftBomb.value) classes.push('gift-bomb');
  if (props.event.tier === '3000') classes.push('tier-3');
  if (props.event.tier === '2000') classes.push('tier-2');
  return classes.join(' ');
});

const updateFromEvent = (event: NormalizedEvent) => {
  const newGiftCount = event.gift_count || 1;
  const wasUpdating = newGiftCount !== currentGiftCount.value && currentIsGiftBomb.value;
  const isFinal = event.raw?.event?.is_final;

  currentUserName.value = event.user_name || 'Anonymous';
  currentGifterName.value = event.gifter_name || event.user_name || 'Anonymous';
  currentGiftCount.value = newGiftCount;
  currentTierLabel.value = props.tierLabels[event.tier || '1000'] || 'Tier 1';
  currentIsGift.value = event.is_gift || false;
  currentIsGiftBomb.value = event.type === 'channel.subscription.gift' && newGiftCount > 1;
  currentMessage.value = event.raw?.event?.message || '';
  currentMonthsSubscribed.value = event.raw?.event?.cumulative_months;
  currentStreakMonths.value = event.raw?.event?.streak_months;

  if (wasUpdating && !isFinal) {
    isAnimating.value = true;
    setTimeout(() => {
      isAnimating.value = false;
    }, 500);
  } else if (isFinal) {
    // Final update - smooth color transition
    isAnimating.value = false;
  }
};

// Watch for prop changes and update the internal state
watch(() => props.event, (newEvent) => {
  updateFromEvent(newEvent);
}, { deep: true, immediate: true });

onMounted(() => {
  updateFromEvent(props.event);
});
</script>

<style scoped>
.subscription-notification {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 80px;
}

.notification-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.notification-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.notification-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: bold;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.notification-message {
  font-size: 1rem;
  line-height: 1.4;
}

.user-message {
  margin-top: 0.5rem;
  padding: 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  font-style: italic;
}

.notification-stats {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  opacity: 0.8;
}

.notification-stats span {
  padding: 0.25rem 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.notification-animation {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  z-index: -1;
}

/* Gift count animation */
.gift-count {
  position: relative;
  display: inline-block;
  font-size: 1.2em;
  color: #ff6b00;
  text-shadow: 0 0 10px currentColor;
  transition: color 0.8s ease, text-shadow 0.8s ease;
}

.gift-count.count-updating {
  animation: count-bounce 0.5s ease-out;
  color: #ffff00;
  text-shadow: 0 0 15px currentColor;
}

@keyframes count-bounce {
  0% { transform: scale(1); }
  50% { transform: scale(1.3); }
  100% { transform: scale(1); }
}

/* Special styles for gift bombs */
.gift-bomb .notification-title {
  animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

/* Tier-specific styles */
.tier-2 {
  border-color: #9146ff !important;
}

.tier-3 {
  border-color: #ff0000 !important;
  background: linear-gradient(135deg, rgba(255, 0, 0, 0.1), rgba(255, 107, 0, 0.1)) !important;
}
</style>
