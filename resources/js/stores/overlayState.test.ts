import type { NormalizedEvent } from '@/types';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { useEventsStore } from './overlayState';

/**
 * A flood's follows reach the overlay as ONE event carrying flood_count
 * (#346, FloodGate on the server). followers_total must move by that number,
 * and an ordinary follow still by one.
 */
function follow(event: Record<string, unknown>): NormalizedEvent {
  return { type: 'channel.follow', raw: { subscription: { type: 'channel.follow' }, event } } as unknown as NormalizedEvent;
}

describe('followers_total', () => {
  beforeEach(() => setActivePinia(createPinia()));

  it('adds a flood event by its flood_count, an ordinary follow by one', () => {
    const store = useEventsStore();

    store.processEventRules(follow({ user_name: 'Alice' }));
    store.processEventRules(follow({ user_name: 'Viewer299', flood_count: 200 }));
    store.processEventRules(follow({ user_name: 'Bob' }));

    expect(store.tags.followers_total).toBe(202);
    expect(store.tags.followers_latest_user_name).toBe('Bob');
  });

  it('names the newest follower a flood event carries', () => {
    const store = useEventsStore();

    store.processEventRules(follow({ user_name: 'Viewer299', flood_count: 200 }));

    expect(store.tags.followers_latest_user_name).toBe('Viewer299');
  });
});
