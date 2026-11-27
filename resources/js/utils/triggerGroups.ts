/**
 * The tiles the Triggers picker opens on: Twitch event types sorted into a
 * handful of groups a streamer recognises. Purely a display grouping - the
 * event types themselves come from EventTemplateMapping::EVENT_TYPES, and a
 * type this table does not know lands in "Other" instead of disappearing.
 */
export interface TwitchTriggerGroup {
  key: string;
  title: string;
}

const BURST_PREFIX = 'burst.';

// Order is tile order. A type matches the first group whose prefix it starts
// with, after any burst prefix is taken off (a burst lives beside its event).
const GROUPS: Array<TwitchTriggerGroup & { prefixes: string[] }> = [
  {
    key: 'audience',
    title: 'Audience',
    prefixes: ['channel.follow', 'channel.subscribe', 'channel.subscription.'],
  },
  { key: 'support', title: 'Support', prefixes: ['channel.cheer', 'channel.raid'] },
  {
    key: 'channel',
    title: 'Channel and stream',
    prefixes: ['channel.channel_points_', 'stream.', 'channel.update'],
  },
  { key: 'hype_train', title: 'Hype Train', prefixes: ['channel.hype_train.'] },
  { key: 'charity', title: 'Charity', prefixes: ['channel.charity_campaign.'] },
  { key: 'goals', title: 'Goals', prefixes: ['channel.goal.'] },
  { key: 'polls', title: 'Polls', prefixes: ['channel.poll.'] },
  { key: 'predictions', title: 'Predictions', prefixes: ['channel.prediction.'] },
];

export const OTHER_TWITCH_GROUP: TwitchTriggerGroup = { key: 'other', title: 'Other' };

/** Every group in tile order, "Other" last. */
export const TWITCH_TRIGGER_GROUPS: TwitchTriggerGroup[] = [...GROUPS.map(({ key, title }) => ({ key, title })), OTHER_TWITCH_GROUP];

export function twitchTriggerGroup(eventType: string): TwitchTriggerGroup {
  const base = eventType.startsWith(BURST_PREFIX) ? eventType.slice(BURST_PREFIX.length) : eventType;
  const group = GROUPS.find((g) => g.prefixes.some((p) => base.startsWith(p)));
  return group ? { key: group.key, title: group.title } : OTHER_TWITCH_GROUP;
}

/**
 * "Ko-fi Donation" reads as "Donation" inside the Ko-fi group: the service is
 * already the group's name. Labels that do not start with it are left alone.
 */
export function stripServicePrefix(label: string, serviceLabel: string): string {
  const prefix = `${serviceLabel} `;
  return label.startsWith(prefix) && label.length > prefix.length ? label.slice(prefix.length) : label;
}
