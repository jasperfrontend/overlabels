import { describe, expect, it } from 'vitest';
import { stripServicePrefix, twitchTriggerGroup } from './triggerGroups';

describe('twitchTriggerGroup', () => {
  it.each([
    ['channel.follow', 'audience'],
    ['burst.channel.follow', 'audience'],
    ['channel.subscription.gift', 'audience'],
    ['burst.channel.subscription.message', 'audience'],
    ['channel.cheer', 'support'],
    ['channel.raid', 'support'],
    ['channel.channel_points_custom_reward_redemption.add', 'channel'],
    ['stream.online', 'channel'],
    ['channel.update', 'channel'],
    ['channel.hype_train.progress', 'hype_train'],
    ['channel.charity_campaign.donate', 'charity'],
    ['channel.goal.end', 'goals'],
    ['channel.poll.begin', 'polls'],
    ['channel.prediction.lock', 'predictions'],
  ])('puts %s in %s', (eventType, key) => {
    expect(twitchTriggerGroup(eventType).key).toBe(key);
  });

  it('keeps an unknown type visible under Other', () => {
    expect(twitchTriggerGroup('channel.something.new').key).toBe('other');
  });
});

describe('stripServicePrefix', () => {
  it('drops the service name the group already shows', () => {
    expect(stripServicePrefix('Ko-fi Donation', 'Ko-fi')).toBe('Donation');
  });

  it('leaves a label that is only the service name, or does not start with it', () => {
    expect(stripServicePrefix('Chat Checkin', 'Chat Checkin')).toBe('Chat Checkin');
    expect(stripServicePrefix('Donation', 'Ko-fi')).toBe('Donation');
  });
});
