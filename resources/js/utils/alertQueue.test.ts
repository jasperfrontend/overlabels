import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAlertQueue, type DropReason } from './alertQueue';

// A recorder for what reaches the screen, in order.
function harness(opts: { gapMs?: number; maxWaiting?: number; maxWaitMs?: number } = {}) {
  const log: string[] = [];
  const dropped: Array<[string, DropReason]> = [];
  const queue = createAlertQueue<string, string>({
    show: (ctx, source) => log.push(`show ${ctx} (${source})`),
    hide: () => log.push('hide'),
    onDrop: (ctx, reason) => dropped.push([ctx, reason]),
    gapMs: opts.gapMs ?? 500,
    maxWaiting: opts.maxWaiting,
    maxWaitMs: opts.maxWaitMs,
  });
  const add = (ctx: string, timestamp: number, source: string | Promise<string | null> | null = ctx, duration = 6000) =>
    queue.enqueue({ context: ctx, timestamp, duration, source });
  return { queue, log, dropped, add };
}

describe('alertQueue', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('shows an alert at once when nothing is on screen', () => {
    const { log, add } = harness();
    add('raid', 100);
    expect(log).toEqual(['show raid (raid)']);
  });

  it('plays back-to-back alerts one after another instead of replacing them', () => {
    // The bug: three raids a second apart showed the first and the last.
    const { log, add } = harness();
    add('a', 100);
    add('b', 101);
    add('c', 101);
    expect(log).toEqual(['show a (a)']);

    // Every hand-over, not just the first: each alert stays its full duration,
    // then the gap passes, and only then does the next one show.
    const handOver = (shown: string[], next: string) => {
      vi.advanceTimersByTime(5999);
      expect(log).toEqual(shown);
      vi.advanceTimersByTime(1);
      expect(log).toEqual([...shown, 'hide']);
      vi.advanceTimersByTime(499);
      expect(log).toEqual([...shown, 'hide']);
      vi.advanceTimersByTime(1);
      expect(log).toEqual([...shown, 'hide', next]);
    };
    handOver(['show a (a)'], 'show b (b)');
    handOver(['show a (a)', 'hide', 'show b (b)'], 'show c (c)');
    vi.advanceTimersByTime(6000);
    expect(log).toEqual(['show a (a)', 'hide', 'show b (b)', 'hide', 'show c (c)', 'hide']);
  });

  it('orders waiting alerts by timestamp, ties in arrival order', () => {
    const { log, add } = harness();
    add('first', 100);
    add('late-stamp', 105);
    add('early-stamp', 102);
    add('same-a', 103);
    add('same-b', 103);
    vi.advanceTimersByTime(4 * 6500);
    expect(log.filter((l) => l.startsWith('show'))).toEqual([
      'show first (first)',
      'show early-stamp (early-stamp)',
      'show same-a (same-a)',
      'show same-b (same-b)',
      'show late-stamp (late-stamp)',
    ]);
  });

  it('keeps the place of an alert whose source is still loading', async () => {
    const { log, add } = harness();
    let resolve!: (s: string | null) => void;
    add('fetched', 100, new Promise((r) => (resolve = r)));
    add('inline', 101);
    expect(log).toEqual([]);

    resolve('src');
    await Promise.resolve();
    expect(log).toEqual(['show fetched (src)']);
    vi.advanceTimersByTime(6500);
    expect(log.at(-1)).toBe('show inline (inline)');
  });

  it('skips an alert whose source cannot be loaded, and reports it', async () => {
    const { log, dropped, add } = harness();
    add('broken', 100, Promise.resolve(null));
    add('fine', 101);
    await Promise.resolve();
    await Promise.resolve();
    expect(log).toEqual(['show fine (fine)']);
    expect(dropped).toEqual([['broken', 'unavailable']]);
  });

  it('drops the oldest waiting alert when the queue overflows', () => {
    const { log, dropped, add } = harness({ maxWaiting: 2 });
    add('on-screen', 100);
    add('w1', 101);
    add('w2', 102);
    add('w3', 103);
    expect(dropped).toEqual([['w1', 'overflow']]);
    vi.advanceTimersByTime(3 * 6500);
    expect(log.filter((l) => l.startsWith('show'))).toEqual(['show on-screen (on-screen)', 'show w2 (w2)', 'show w3 (w3)']);
  });

  it('drops an alert that waited too long instead of showing it late', () => {
    const { log, dropped, add } = harness({ maxWaitMs: 10_000 });
    add('long', 100, 'long', 15_000);
    add('waits', 101);
    vi.advanceTimersByTime(15_500);
    expect(dropped).toEqual([['waits', 'stale']]);
    expect(log).toEqual(['show long (long)', 'hide']);
  });

  it('gives up on a load that never settles once it has waited too long', () => {
    const { log, dropped, add } = harness({ maxWaitMs: 10_000 });
    add('hung', 100, new Promise(() => {}));
    add('behind', 101);
    vi.advanceTimersByTime(10_002);
    expect(dropped.map(([ctx]) => ctx)).toEqual(['hung', 'behind']);
    expect(log).toEqual([]);
  });

  it('updates a live alert on screen in place and restarts its clock', () => {
    // A poll fires progress over and over; queueing would replay it twenty times.
    const log: string[] = [];
    const queue = createAlertQueue<string, string>({ show: (ctx) => log.push(`show ${ctx}`), hide: () => log.push('hide') });
    queue.enqueue({ context: 'poll 10%', timestamp: 100, duration: 6000, source: 's', key: 'progress:poll' });
    vi.advanceTimersByTime(4000);
    queue.enqueue({ context: 'poll 40%', timestamp: 104, duration: 6000, source: 's', key: 'progress:poll' });
    vi.advanceTimersByTime(4000);
    expect(log).toEqual(['show poll 10%', 'show poll 40%']);
    vi.advanceTimersByTime(2000);
    expect(log).toEqual(['show poll 10%', 'show poll 40%', 'hide']);
  });

  it('lets a live update replace its waiting predecessor where it stands', () => {
    const { log, queue, add } = harness();
    add('raid', 100);
    queue.enqueue({ context: 'poll 10%', timestamp: 101, duration: 6000, source: 'p', key: 'progress:poll' });
    add('cheer', 102);
    queue.enqueue({ context: 'poll 60%', timestamp: 103, duration: 6000, source: 'p', key: 'progress:poll' });
    expect(queue.size()).toBe(2);
    vi.advanceTimersByTime(3 * 6500);
    expect(log.filter((l) => l.startsWith('show'))).toEqual(['show raid (raid)', 'show poll 60% (p)', 'show cheer (cheer)']);
  });

  it('never merges two alerts without a key, even for the same alert template', () => {
    const { log, add } = harness();
    add('raid 33k', 100, 'raid-template');
    add('raid 25k', 100, 'raid-template');
    vi.advanceTimersByTime(6500);
    expect(log.filter((l) => l.startsWith('show'))).toEqual(['show raid 33k (raid-template)', 'show raid 25k (raid-template)']);
  });

  it('shows nothing more after clear()', () => {
    const { queue, log, add } = harness();
    add('a', 100);
    add('b', 101);
    queue.clear();
    vi.advanceTimersByTime(20_000);
    add('c', 102);
    expect(log).toEqual(['show a (a)']);
  });
});
