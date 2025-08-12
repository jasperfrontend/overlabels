// EventSub → template tag mapping rules
export const EVENT_RULES: Record<string, Array<any>> = {
  // FOLLOWS
  'channel.follow': [
    { op: 'inc', tag: 'followers_total', by: 1 },
    { op: 'set', tag: 'followers_latest_user_name', from: 'event.user_name' },
    { op: 'set', tag: 'followers_latest_user_id', from: 'event.user_id' },
    { op: 'set', tag: 'followers_latest_date', from: 'event.followed_at' },
  ],

  // SUBS (new sub message)
  'channel.subscribe': [
    { op: 'inc', tag: 'subscribers_total', by: 1 },
    { op: 'set', tag: 'subscribers_latest_user_name', from: 'event.user_name' },
    { op: 'set', tag: 'subscribers_latest_tier', from: 'event.tier' },
    { op: 'set', tag: 'subscribers_latest_is_gift', from: 'event.is_gift' },
    { op: 'set', tag: 'subscribers_latest_gifter_name', from: 'event.gifter_name' },
    { op: 'do', tag: 'new_subscriber_overlay', value: true }, // show overlay on new sub
  ],

  // MASS GIFT (gift bomb)
  'channel.subscription.gift': [
    // Twitch CLI often sends "total" for count this message gifted
    { op: 'inc', tag: 'subscribers_total', byPath: 'event.total' },
    { op: 'set', tag: 'subscribers_latest_is_gift', value: true },
    { op: 'set', tag: 'subscribers_latest_gifter_name', from: 'event.user_name' },
  ],

  // CHEER
  'channel.cheer': [
    { op: 'set', tag: 'last_cheer_user', from: 'event.user_name' },
    { op: 'set', tag: 'last_cheer_bits', from: 'event.bits' },
  ],

  // RAID
  'channel.raid': [
    { op: 'set', tag: 'last_raid_from', from: 'event.from_broadcaster_user_name' },
    { op: 'max', tag: 'last_raid_viewers_peak', value: 0 }, // ensures numeric init
    { op: 'max', tag: 'last_raid_viewers_peak', from: 'event.viewers' },
  ],
};
