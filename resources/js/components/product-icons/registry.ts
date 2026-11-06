import type { Component } from 'vue';
import ChatCheckin from './ChatCheckin.vue';
import ChatEmoteBubbles from './ChatEmoteBubbles.vue';
import ChatTower from './ChatTower.vue';
import FollowerBowling from './FollowerBowling.vue';
import TwitchChatOverlay from './TwitchChatOverlay.vue';

/**
 * Product slug -> its icon. A new product's icon is one file in this folder
 * and one line here. A product without an entry has no icon, and callers
 * fall back to its service logo or hero.
 */
export const PRODUCT_ICONS: Record<string, Component> = {
  'chat-checkin': ChatCheckin,
  'chat-emote-bubbles': ChatEmoteBubbles,
  'chat-tower': ChatTower,
  'follower-bowling': FollowerBowling,
  'twitch-chat-overlay': TwitchChatOverlay,
};

export function hasProductIcon(slug: string): boolean {
  return slug in PRODUCT_ICONS;
}
