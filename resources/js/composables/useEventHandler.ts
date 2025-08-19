import { normalizeEvent } from './useNormalizeEvent';
import type { NormalizedEvent } from '@/types';

export interface EventHandlerConfig {
  enableLogging?: boolean;
  enableNotifications?: boolean;
  enableStatistics?: boolean;
}

/**
 * Composable: useEventHandler
 *
 * Provides a unified interface for handling Twitch EventSub events.
 * Wraps event normalization with optional logging, notification dispatching,
 * and statistics tracking via `CustomEvent`s on `window`.
 *
 * This composable is intended to be the main entry point for consuming raw
 * EventSub payloads in a Vue application.
 *
 * @param config - Optional configuration:
 *  - `enableLogging` (default: true): Dispatch logs of normalized events
 *  - `enableNotifications` (default: true): Dispatch `twitch-event-normalized`
 *  - `enableStatistics` (default: true): Dispatch `twitch-event-stats`
 *
 * @returns An object with the following methods:
 * - `processRawEvent(raw: any): NormalizedEvent` — Normalize and process a raw EventSub payload
 * - `dispatchEvent(event: NormalizedEvent): void` — Dispatch normalized events as `CustomEvent`s
 * - `handleEvent(raw: any): NormalizedEvent` — Wrapper around `processRawEvent` + `dispatchEvent`
 * - `isEventType(event: NormalizedEvent, ...types: string[]): boolean` — Check if an event matches a type
 * - `getEventMetadata(event: NormalizedEvent): Record<string, any>` — Extracts contextual metadata (bits, subs, raids, etc.)
 * - `shouldGroupEvents(event1: NormalizedEvent, event2: NormalizedEvent): boolean` — Whether two events should be grouped together
 *
 * @example
 * ```ts
 * import { useEventHandler } from '@/composables/useEventHandler'
 *
 * const { handleEvent, isEventType } = useEventHandler({ enableLogging: true })
 *
 * // Handle a raw Twitch EventSub payload
 * window.addEventListener('message', (e) => {
 *   const event = handleEvent(e.data)
 *   if (isEventType(event, 'channel.subscribe')) {
 *     console.log('New subscriber:', event.user_name)
 *   }
 * })
 *
 * // Listen globally for normalized events
 * window.addEventListener('twitch-event-normalized', (e) => {
 *   console.log('Normalized event:', e.detail)
 * })
 * ```
 */
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
    if (enableLogging) {
      window.dispatchEvent(new CustomEvent('twitch-event-log', {
        detail: event
      }))
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
    console.log("💥 event: ",event);
    switch (event.type) {
      case 'channel.subscribe':
      case 'channel.subscription.message':
      case 'channel.subscription.gift':
        if (event.tier) metadata.tier = event.tier;
        if (event.is_gift) metadata.gift = true;
        if (event.gift_count) metadata.count = event.gift_count;
        if (event.cumulative_total) metadata.cumulative_total = event.cumulative_total; // total gifted subs in the channel
        break;

      case 'channel.cheer':
        const bits = event.raw?.event?.bits;
        if (bits) metadata.bits = bits;
        break;

      case 'channel.raid':
        const viewers = event.raw?.event?.viewers;
        const to_broadcaster_user_id = event.raw?.event?.to_broadcaster_user_id;
        const to_broadcaster_user_login = event.raw?.event?.to_broadcaster_user_login;
        const to_broadcaster_user_name = event.raw?.event?.to_broadcaster_user_name;
        const from_broadcaster_user_id = event.raw?.event?.from_broadcaster_user_id;
        const from_broadcaster_user_login = event.raw?.event?.from_broadcaster_user_login;
        const from_broadcaster_user_name = event.raw?.event?.from_broadcaster_user_name;
        if (viewers) metadata.viewers = viewers;
        if (to_broadcaster_user_id) metadata.to_broadcaster_user_id = to_broadcaster_user_id;
        if (to_broadcaster_user_login) metadata.to_broadcaster_user_login = to_broadcaster_user_login;
        if (to_broadcaster_user_name) metadata.to_broadcaster_user_name = to_broadcaster_user_name;
        if (from_broadcaster_user_id) metadata.from_broadcaster_user_id = from_broadcaster_user_id;
        if (from_broadcaster_user_login) metadata.from_broadcaster_user_login = from_broadcaster_user_login;
        if (from_broadcaster_user_name) metadata.from_broadcaster_user_name = from_broadcaster_user_name;
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
