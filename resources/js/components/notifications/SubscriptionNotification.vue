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
          {{ title }}
        </h3>
        
        <div class="notification-message" :style="{ color: messageColor }">
          <template v-if="isGiftBomb">
            <strong>{{ gifterName }}</strong> just gifted 
            <strong class="gift-count" :class="{ 'count-updating': isUpdating }">{{ giftCount }}</strong> 
            {{ tierLabel }} subscriptions to the community!
          </template>
          <template v-else-if="isGift">
            <strong>{{ userName }}</strong> received a {{ tierLabel }} subscription gift from <strong>{{ gifterName }}</strong>!
          </template>
          <template v-else>
            <strong>{{ userName }}</strong> subscribed with {{ tierLabel }}!
          </template>
          
          <div v-if="showMessage && message" class="user-message">
            "{{ message }}"
          </div>
        </div>
        
        <div v-if="showStats" class="notification-stats">
          <span v-if="monthsSubscribed">{{ monthsSubscribed }} months</span>
          <span v-if="streakMonths">{{ streakMonths }} month streak</span>
        </div>
      </div>
      
      <div v-if="showAnimation" class="notification-animation">
        <slot name="animation" />
      </div>
    </div>
  </NotificationBase>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import NotificationBase, { type NotificationBaseProps } from './NotificationBase.vue';
import type { NormalizedEvent } from '@/types';

export interface SubscriptionNotificationProps extends NotificationBaseProps {
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

const props = withDefaults(defineProps<SubscriptionNotificationProps>(), {
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

const baseProps = computed(() => {
  const { event, count, showIcon, showMessage, showStats, showAnimation, titleColor, messageColor, iconComponent, tierLabels, ...rest } = props;
  return rest;
});

// Suppress unused variable warnings
void (props.event && props.count && props.showIcon && props.showMessage && props.showStats && props.showAnimation && props.titleColor && props.messageColor && props.iconComponent && props.tierLabels);

const isGift = computed(() => props.event.is_gift);
const isGiftBomb = computed(() => props.event.type === 'channel.subscription.gift' && (props.event.gift_count || 0) > 1);

const userName = computed(() => props.event.user_name || 'Anonymous');
const gifterName = computed(() => props.event.gifter_name || props.event.user_name || 'Anonymous');
const giftCount = computed(() => props.event.gift_count || 1);
const isUpdating = computed(() => props.event.raw?.event?.is_live_update || false);

const tierLabel = computed(() => {
  const tier = props.event.tier || '1000';
  return props.tierLabels[tier] || 'Tier 1';
});

const title = computed(() => {
  if (isGiftBomb.value) {
    const count = giftCount.value;
    if (count >= 100) return '🔥💎 MASSIVE GIFT BOMB! 💎🔥';
    if (count >= 50) return '🎆💥 HUGE GIFT BOMB! 💥🎆';
    if (count >= 20) return '🎉🎁 BIG GIFT BOMB! 🎁🎉';
    if (count >= 10) return '🎉 GIFT BOMB! 🎉';
    return '🎁 Gift Bomb! 🎁';
  }
  if (isGift.value) {
    return `${userName.value} got a gift sub!`;
  }
  return `${userName.value} subscribed!`;
});

const message = computed(() => props.event.raw?.event?.message || '');
const monthsSubscribed = computed(() => props.event.raw?.event?.cumulative_months);
const streakMonths = computed(() => props.event.raw?.event?.streak_months);

const customClass = computed(() => {
  const classes = ['subscription-notification-wrapper'];
  if (isGiftBomb.value) classes.push('gift-bomb');
  if (props.event.tier === '3000') classes.push('tier-3');
  if (props.event.tier === '2000') classes.push('tier-2');
  return classes.join(' ');
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