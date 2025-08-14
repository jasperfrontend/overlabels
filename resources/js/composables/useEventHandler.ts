import { normalizeEvent } from './useNormalizeEvent';
import type { NormalizedEvent } from '@/types';

export interface EventHandlerConfig {
  enableLogging?: boolean;
  enableNotifications?: boolean;
  enableStatistics?: boolean;
}

export function useEventHandler(config: EventHandlerConfig = {}) {
  const {
    enableLogging = true,
    enableNotifications = true,
    enableStatistics = true,
  } = config;

  const processRawEvent = (rawEvent: any): NormalizedEvent => {
    return normalizeEvent(rawEvent);
  };

  const dispatchEvent = (event: NormalizedEvent) => {
    if (enableNotifications) {
      window.dispatchEvent(new CustomEvent('twitch-event-normalized', {
        detail: event
      }));
    }

    if (enableStatistics) {
      window.dispatchEvent(new CustomEvent('twitch-event-stats', {
        detail: event
      }));
    }
  };

  const handleEvent = (rawEvent: any) => {
    try {
      const normalizedEvent = processRawEvent(rawEvent);
      dispatchEvent(normalizedEvent);
      return normalizedEvent;
    } catch (error) {
      console.error('[useEventHandler] Error processing event:', error, rawEvent);
      throw error;
    }
  };

  const isEventType = (event: NormalizedEvent, ...types: string[]): boolean => {
    return types.includes(event.type);
  };

  const getEventMetadata = (event: NormalizedEvent) => {
    const metadata: Record<string, any> = {};

    switch (event.type) {
      case 'channel.subscribe':
      case 'channel.subscription.message':
      case 'channel.subscription.gift':
        if (event.tier) metadata.tier = event.tier;
        if (event.is_gift) metadata.gift = true;
        if (event.gift_count) metadata.count = event.gift_count;
        break;

      case 'channel.cheer':
        const bits = event.raw?.event?.bits;
        if (bits) metadata.bits = bits;
        break;

      case 'channel.raid':
        const viewers = event.raw?.event?.viewers;
        if (viewers) metadata.viewers = viewers;
        break;

      default:
        Object.keys(event.raw?.event || {}).forEach(key => {
          if (typeof event.raw.event[key] === 'number' ||
              typeof event.raw.event[key] === 'string') {
            metadata[key] = event.raw.event[key];
          }
        });
    }

    return metadata;
  };

  const shouldGroupEvents = (event1: NormalizedEvent, event2: NormalizedEvent): boolean => {
    if (event1.type !== event2.type) return false;

    const groupableTypes = [
      'channel.subscription.gift',
      'channel.subscribe',
      'channel.follow',
      'channel.cheer',
    ];

    if (!groupableTypes.includes(event1.type)) return false;

    if (event1.type === 'channel.subscription.gift' && event2.type === 'channel.subscription.gift') {
      return event1.gifter_name === event2.gifter_name;
    }

    const timeDiff = Math.abs(event1.ts - event2.ts);
    return timeDiff < 3000;
  };

  return {
    processRawEvent,
    dispatchEvent,
    handleEvent,
    isEventType,
    getEventMetadata,
    shouldGroupEvents,
  };
}
