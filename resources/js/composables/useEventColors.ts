export interface UnifiedEvent {
  id: number;
  source: string;
  event_type: string;
  created_at: string;
  event_data?: Record<string, unknown> | null;
  normalized_payload?: Record<string, unknown> | null;
}

export function useEventColors() {
  function eventColor(event: UnifiedEvent): string {
    const type = event.event_type;
    const source = event.source;
    if (type === 'channel.subscribe') return 'purple-500';
    if (type === 'channel.subscription.gift') return 'pink-500';
    if (type === 'channel.subscription.message') return 'indigo-500';
    if (type === 'channel.raid') return 'rose-500';
    if (type === 'channel.cheer') return 'amber-500';
    if (type === 'stream.online') return 'green-500';
    if (type === 'stream.offline') return 'red-500';
    if (type === 'channel.channel_points_custom_reward_redemption.add') return 'cyan-500';
    if (type === 'channel.follow') return 'green-500';
    if (type === 'donation' && source === 'kofi') return '[#ff5a16]';
    if (type === 'subscription' && source === 'kofi') return '[#ff5a16]';
    if (type === 'shop_order' && source === 'kofi') return '[#ff5a16]';
    if (type === 'commission' && source === 'kofi') return '[#ff5a16]';
    if (type === 'donation' && source === 'streamlabs') return '[#80f5d2]';
    if (type === 'subscription' && source === 'streamlabs') return '[#80f5d2]';
    if (type === 'shop_order' && source === 'streamlabs') return '[#80f5d2]';
    if (type === 'commission' && source === 'streamlabs') return '[#80f5d2]';
    return 'slate-500';
  }

  function eventDotClass(event: UnifiedEvent): string {
    return `bg-${eventColor(event)}`;
  }

  function eventHoverBorderClass(event: UnifiedEvent): string {
    return `hover:border-l-${eventColor(event)}`;
  }

  return { eventColor, eventDotClass, eventHoverBorderClass };
}
