export interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  label?: string | null;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

// Full class strings so Tailwind can detect them during production builds.
// Dynamic construction like `bg-${color}` is invisible to Tailwind's scanner.
const EVENT_STYLES: Record<string, { dot: string; border: string }> = {
  'channel.subscribe': { dot: 'text-purple-500', border: 'hover:border-l-purple-500' },
  'channel.subscription.gift': { dot: 'text-pink-500', border: 'hover:border-l-pink-500' },
  'channel.subscription.message': { dot: 'text-indigo-500', border: 'hover:border-l-indigo-500' },
  'channel.raid': { dot: 'text-rose-500', border: 'hover:border-l-rose-500' },
  'channel.cheer': { dot: 'text-amber-500', border: 'hover:border-l-amber-500' },
  'stream.online': { dot: 'text-green-500', border: 'hover:border-l-green-500' },
  'stream.offline': { dot: 'text-red-500', border: 'hover:border-l-red-500' },
  'channel.channel_points_custom_reward_redemption.add': { dot: 'text-cyan-500', border: 'hover:border-l-cyan-500' },
  'channel.channel_points_custom_reward_redemption.update': { dot: 'text-cyan-500', border: 'hover:border-l-cyan-500' },
  'channel.follow': { dot: 'text-green-500', border: 'hover:border-l-green-500' },
  'channel.poll.begin': { dot: 'text-[#8c45f7]', border: 'hover:border-l-[#8c45f7]' },
  'channel.poll.progress': { dot: 'text-[#8c45f7]', border: 'hover:border-l-[#8c45f7]' },
  'channel.poll.end': { dot: 'text-[#8c45f7]', border: 'hover:border-l-[#8c45f7]' },
};

const SOURCE_STYLES: Record<string, { dot: string; border: string }> = {
  kofi: { dot: 'fill-[#ff5a16]', border: 'hover:border-l-[#ff5a16]' },
  streamlabs: { dot: 'fill-[#80f5d2]', border: 'hover:border-l-[#80f5d2]' },
  streamelements: { dot: 'fill-[#0691ff]', border: 'hover:border-l-[#0691ff]' },
  twitch: { dot: 'fill-[#9146ff]', border: 'hover:border-l-[#9146ff]' },
  bmac: { dot: 'fill-[#ffdd00]', border: 'hover:border-l-[#ffdd00]' },
  fourthwall: { dot: 'fill-[#0b48f9]', border: 'hover:border-l-[#0b48f9]' },
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
  'channel.channel_points_custom_reward_redemption.update': 'Points (updated)',
  'channel.poll.begin': 'Poll started',
  'channel.poll.progress': 'Poll progress',
  'channel.poll.end': 'Poll ended',
  'channel.hype_train.begin': 'Hype train started',
  'channel.hype_train.progress': 'Hype train progress',
  'channel.hype_train.end': 'Hype train ended',
  'channel.goal.begin': 'Goal started',
  'channel.goal.progress': 'Goal progress',
  'channel.goal.end': 'Goal ended',
  'stream.online': 'Online',
  'stream.offline': 'Offline',
  donation: 'Donation',
  subscription: 'Subscription',
  shop_order: 'Shop Order',
  commission: 'Commission',
  recurring: 'Recurring',
  extra: 'Extra',
  membership: 'Membership',
  wishlist: 'Wishlist',
  location_update: 'Location Update',
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
