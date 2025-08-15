import { ref } from 'vue';
import type { NormalizedEvent } from '@/types';

interface GiftBombBuffer {
  gifterName: string;
  gifterUserId: string;
  tier: "1000" | "2000" | "3000" | undefined;
  events: NormalizedEvent[];
  firstEventTime: number;
  lastEventTime: number;
  lastUpdateTime: number;
  timeoutId: number | null;
  isLive: boolean;
  liveEventId: string;
}

export function useGiftBombDetector() {
  const activeBuffers = ref<Map<string, GiftBombBuffer>>(new Map());
  const GIFT_BOMB_WINDOW = 8000; // 5 seconds to collect all gift events (faster)
  const MIN_GIFT_BOMB_SIZE = 2; // Minimum 2 gifts to be considered a bomb
  const LIVE_UPDATE_DELAY = 100; // 200 ms delay before showing the first live notification

  const createGiftBombEvent = (buffer: GiftBombBuffer, isUpdate = false): NormalizedEvent => {
    const firstEvent = buffer.events[0];
    const giftCount = buffer.events.length;

    return {
      id: buffer.liveEventId,
      type: 'channel.subscription.gift',
      ts: buffer.firstEventTime,
      broadcaster_user_id: firstEvent.broadcaster_user_id,
      broadcaster_user_login: firstEvent.broadcaster_user_login,
      broadcaster_user_name: firstEvent.broadcaster_user_name,
      user_id: buffer.gifterUserId,
      user_login: firstEvent.user_login,
      user_name: buffer.gifterName,
      gifter_name: buffer.gifterName,
      tier: buffer.tier,
      is_gift: true,
      gift_count: giftCount,
      raw: {
        subscription: {
          type: 'channel.subscription.gift'
        },
        event: {
          user_id: buffer.gifterUserId,
          user_name: buffer.gifterName,
          user_login: firstEvent.user_login,
          broadcaster_user_id: firstEvent.broadcaster_user_id,
          broadcaster_user_login: firstEvent.broadcaster_user_login,
          broadcaster_user_name: firstEvent.broadcaster_user_name,
          tier: buffer.tier,
          total: giftCount,
          is_gift: true,
          is_live_update: isUpdate,
        }
      }
    };
  };

  const flushBuffer = (bufferKey: string, callback: (event: NormalizedEvent) => void) => {
    const buffer = activeBuffers.value.get(bufferKey);
    if (!buffer) return;

    if (buffer.events.length >= MIN_GIFT_BOMB_SIZE) {
      // Always send a final event to ensure proper completion
      const finalEvent = createGiftBombEvent(buffer, false); // Final event, not an update
      finalEvent.raw.event.is_final = true;
      callback(finalEvent);
    } else {
      // Send individual events if below threshold
      buffer.events.forEach(event => callback(event));
    }

    // Clean up
    if (buffer.timeoutId) {
      clearTimeout(buffer.timeoutId);
    }
    activeBuffers.value.delete(bufferKey);
  };

  const sendLiveUpdate = (buffer: GiftBombBuffer, callback: (event: NormalizedEvent) => void) => {
    const liveEvent = createGiftBombEvent(buffer, true);
    callback(liveEvent);
  };

  const processGiftEvent = (event: NormalizedEvent, callback: (event: NormalizedEvent) => void) => {
    // Only process gift subscription events
    if (event.type !== 'channel.subscribe' || !event.is_gift) {
      callback(event);
      return;
    }

    const gifterName = event.user_name || 'Anonymous';
    const gifterUserId = event.user_id || 'unknown';
    const tier: "1000" | "2000" | "3000" | undefined = event.tier as "1000" | "2000" | "3000" | undefined;
    const bufferKey = `${gifterUserId}-${tier || '1000'}`;
    const now = Date.now();

    let buffer = activeBuffers.value.get(bufferKey);

    if (!buffer) {
      // Create new buffer
      buffer = {
        gifterName,
        gifterUserId,
        tier,
        events: [],
        firstEventTime: now,
        lastEventTime: now,
        lastUpdateTime: 0,
        timeoutId: null,
        isLive: false,
        liveEventId: `gift-bomb-${gifterUserId}-${now}`,
      };
      activeBuffers.value.set(bufferKey, buffer);
    }

    // Add event to buffer
    buffer.events.push(event);
    buffer.lastEventTime = now;

    // Clear existing timeout
    if (buffer.timeoutId) {
      clearTimeout(buffer.timeoutId);
    }

    // If this is the second gift, start the live notification
    if (buffer.events.length === MIN_GIFT_BOMB_SIZE && !buffer.isLive) {
      buffer.isLive = true;
      buffer.lastUpdateTime = now;
      // Capture buffer in closure to ensure it's defined
      const currentBuffer = buffer;
      setTimeout(() => {
        // Re-check that buffer still exists before sending update
        const latestBuffer = activeBuffers.value.get(bufferKey);
        if (latestBuffer) {
          sendLiveUpdate(latestBuffer, callback);
        }
      }, LIVE_UPDATE_DELAY);
    }

    // If already live, send throttled update (every 5th event or every 300ms)
    else if (buffer.isLive && buffer.events.length > MIN_GIFT_BOMB_SIZE) {
      const shouldUpdate = buffer.events.length % 5 === 0 || (now - buffer.lastUpdateTime) > 300;
      if (shouldUpdate) {
        buffer.lastUpdateTime = now;
        sendLiveUpdate(buffer, callback);
      }
    }

    // Set new timeout to finalize the gift bomb
    buffer.timeoutId = window.setTimeout(() => {
      flushBuffer(bufferKey, callback);
    }, GIFT_BOMB_WINDOW);
  };

  const processEvent = (event: NormalizedEvent, callback: (event: NormalizedEvent) => void) => {
    if (event.type === 'channel.subscribe' && event.is_gift) {
      processGiftEvent(event, callback);
    } else {
      // Non-gift events pass through immediately
      callback(event);
    }
  };

  const forceFlushAll = (callback: (event: NormalizedEvent) => void) => {
    const bufferKeys = Array.from(activeBuffers.value.keys());
    bufferKeys.forEach(key => flushBuffer(key, callback));
  };

  const getActiveBuffers = () => {
    return Array.from(activeBuffers.value.values()).map(buffer => ({
      gifterName: buffer.gifterName,
      count: buffer.events.length,
      timeRemaining: buffer.timeoutId ? GIFT_BOMB_WINDOW : 0,
    }));
  };

  return {
    processEvent,
    forceFlushAll,
    getActiveBuffers,
    activeBufferCount: () => activeBuffers.value.size,
  };
}
