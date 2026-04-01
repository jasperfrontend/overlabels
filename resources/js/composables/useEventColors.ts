export interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

// Full class strings so Tailwind can detect them during production builds.
// Dynamic construction like `bg-${color}` is invisible to Tailwind's scanner.
const EVENT_STYLES: Record<string, { dot: string; border: string }> = {
  'channel.subscribe':       { dot: 'bg-purple-500', border: 'hover:border-l-purple-500' },
  'channel.subscription.gift': { dot: 'bg-pink-500', border: 'hover:border-l-pink-500' },
  'channel.subscription.message': { dot: 'bg-indigo-500', border: 'hover:border-l-indigo-500' },
  'channel.raid':            { dot: 'bg-rose-500', border: 'hover:border-l-rose-500' },
  'channel.cheer':           { dot: 'bg-amber-500', border: 'hover:border-l-amber-500' },
  'stream.online':           { dot: 'bg-green-500', border: 'hover:border-l-green-500' },
  'stream.offline':          { dot: 'bg-red-500', border: 'hover:border-l-red-500' },
  'channel.channel_points_custom_reward_redemption.add': { dot: 'bg-cyan-500', border: 'hover:border-l-cyan-500' },
  'channel.follow':          { dot: 'bg-green-500', border: 'hover:border-l-green-500' },
};

const SOURCE_STYLES: Record<string, { dot: string; border: string }> = {
  kofi:       { dot: 'bg-[#ff5a16]', border: 'hover:border-l-[#ff5a16]' },
  streamlabs: { dot: 'bg-[#80f5d2]', border: 'hover:border-l-[#80f5d2]' },
};

const DEFAULT_STYLE = { dot: 'bg-slate-500', border: 'hover:border-l-slate-500' };

export const EVENT_TYPE_LABELS: Record<string, string> = {
  'channel.follow': 'Follow',
  'channel.subscribe': 'Subscribe',
  'channel.subscription.gift': 'Gift Sub',
  'channel.subscription.message': 'Re-sub',
  'channel.cheer': 'Cheer',
  'channel.raid': 'Raid',
  'channel.channel_points_custom_reward_redemption.add': 'Points',
  'stream.online': 'Online',
  'stream.offline': 'Offline',
  'donation': 'Donation',
  'subscription': 'Subscription',
  'shop_order': 'Shop Order',
  'commission': 'Commission',
  'location_update': 'Location Update',
};

function resolveStyleByType(eventType: string, source?: string) {
  const byType = EVENT_STYLES[eventType];
  if (byType) return byType;

  if (source) {
    const bySource = SOURCE_STYLES[source];
    if (bySource) return bySource;
  }

  return DEFAULT_STYLE;
}

export function useEventColors() {
  function eventDotClass(event: UnifiedEvent): string {
    return resolveStyleByType(event.event_type, event.source).dot;
  }

  function eventHoverBorderClass(event: UnifiedEvent): string {
    return resolveStyleByType(event.event_type, event.source).border;
  }

  function eventTypeDotClass(eventType: string, source?: string): string {
    return resolveStyleByType(eventType, source).dot;
  }

  function eventTypeHoverBorderClass(eventType: string, source?: string): string {
    return resolveStyleByType(eventType, source).border;
  }

  return { eventDotClass, eventHoverBorderClass, eventTypeDotClass, eventTypeHoverBorderClass };
}
