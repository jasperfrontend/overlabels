/**
 * Alerts take turns. Before this, a new alert replaced whatever was on screen,
 * and an alert whose source had to be fetched was dropped outright if another
 * arrived before the fetch finished - three raids a second apart showed the
 * first and the last. Now every alert waits for the one before it.
 *
 * Order is the alert's own timestamp (whole seconds from the server), ties in
 * arrival order. Nothing jumps the line: no priorities, no grouping. An alert
 * whose source is still loading holds its place, and the ones behind it wait
 * for it rather than overtaking.
 *
 * One exception to taking turns: live-updating alerts (poll, prediction, hype
 * train, goal and charity progress) fire again and again for one ongoing thing.
 * Those carry a `key`; a keyed alert already on screen takes the new data in
 * place and restarts its clock, and one still waiting is replaced where it
 * stands. Queueing them would replay a poll twenty times.
 *
 * Two bounds keep a burst from turning into a backlog nobody wants: at most
 * MAX_WAITING alerts wait (the oldest waiting one goes first when it
 * overflows), and an alert that has waited longer than MAX_WAIT_MS is dropped
 * instead of shown - an announcement minutes late is worse than none.
 *
 * Pure and timer-injectable so it can be tested without a DOM. The core shape
 * (one current item, a waiting list, a display timer that advances it, a size
 * cap) comes from the old useNotificationQueue composable, which was never
 * wired up; its priorities and grouping are deliberately not carried over.
 */

export const GAP_MS = 500;
export const MAX_WAITING = 25;
export const MAX_WAIT_MS = 180_000;

export type DropReason = 'overflow' | 'stale' | 'unavailable';

export interface AlertQueueOptions<C, S> {
  /** Put the alert on screen, or refresh the one there (same key). The queue hides it after its duration. */
  show: (context: C, source: S) => void;
  hide: () => void;
  /** An alert that will never be shown, so its side effects (TTS) can be cancelled. */
  onDrop?: (context: C, reason: DropReason) => void;
  gapMs?: number;
  maxWaiting?: number;
  maxWaitMs?: number;
  now?: () => number;
  setTimer?: (fn: () => void, ms: number) => unknown;
  clearTimer?: (handle: unknown) => void;
}

export interface EnqueueInput<C, S> {
  context: C;
  /** Orders the queue. Compared only, so any consistent unit works. */
  timestamp: number;
  /** How long the alert stays on screen, in ms. */
  duration: number;
  /** The alert's source: given now, or loading. Null means it cannot be shown. */
  source: S | Promise<S | null> | null;
  /** Live updates of one ongoing thing share a key: they refresh rather than queue. */
  key?: string;
}

interface Entry<C, S> {
  seq: number;
  context: C;
  timestamp: number;
  duration: number;
  enqueuedAt: number;
  state: 'loading' | 'ready' | 'unavailable';
  source: S | null;
  key: string | null;
}

export function createAlertQueue<C, S>(options: AlertQueueOptions<C, S>) {
  const gapMs = options.gapMs ?? GAP_MS;
  const maxWaiting = options.maxWaiting ?? MAX_WAITING;
  const maxWaitMs = options.maxWaitMs ?? MAX_WAIT_MS;
  const now = options.now ?? (() => Date.now());
  const setTimer = options.setTimer ?? ((fn: () => void, ms: number) => setTimeout(fn, ms));
  const clearTimer = options.clearTimer ?? ((handle: unknown) => clearTimeout(handle as ReturnType<typeof setTimeout>));

  const waiting: Entry<C, S>[] = [];
  let seq = 0;
  // 'idle' = free to show; 'showing' and 'gap' hold the queue.
  let phase: 'idle' | 'showing' | 'gap' = 'idle';
  let current: Entry<C, S> | null = null;
  let timer: unknown = null;
  // Re-checks a head that is still loading, so it goes stale on time.
  let staleTimer: unknown = null;
  let cleared = false;

  const drop = (entry: Entry<C, S>, reason: DropReason) => options.onDrop?.(entry.context, reason);

  function pump(): void {
    if (cleared || phase !== 'idle') return;
    if (staleTimer !== null) {
      clearTimer(staleTimer);
      staleTimer = null;
    }

    while (waiting.length > 0) {
      const head = waiting[0];

      if (now() - head.enqueuedAt > maxWaitMs) {
        waiting.shift();
        drop(head, 'stale');
        continue;
      }
      if (head.state === 'unavailable') {
        waiting.shift();
        drop(head, 'unavailable');
        continue;
      }
      if (head.state === 'loading') {
        // Its promise calls pump() when it settles; this catches a load that never does.
        staleTimer = setTimer(pump, Math.max(0, head.enqueuedAt + maxWaitMs - now()) + 1);
        return;
      }

      waiting.shift();
      phase = 'showing';
      current = head;
      options.show(head.context, head.source as S);
      timer = setTimer(endCurrent, Math.max(0, head.duration));
      return;
    }
  }

  function endCurrent(): void {
    options.hide();
    current = null;
    phase = 'gap';
    timer = setTimer(() => {
      timer = null;
      phase = 'idle';
      pump();
    }, gapMs);
  }

  function enqueue(input: EnqueueInput<C, S>): void {
    if (cleared) return;
    const entry: Entry<C, S> = {
      seq: seq++,
      context: input.context,
      timestamp: input.timestamp,
      duration: input.duration,
      enqueuedAt: now(),
      state: 'loading',
      source: null,
      key: input.key ?? null,
    };

    if (input.source instanceof Promise) {
      input.source.then(
        (source) => {
          entry.state = source === null ? 'unavailable' : 'ready';
          entry.source = source;
          pump();
        },
        () => {
          entry.state = 'unavailable';
          pump();
        },
      );
    } else if (input.source === null) {
      entry.state = 'unavailable';
    } else {
      entry.state = 'ready';
      entry.source = input.source;
    }

    if (entry.key !== null) {
      // A live update for the alert on screen: new data in place, clock restarted.
      if (phase === 'showing' && current !== null && current.key === entry.key && entry.state === 'ready') {
        current = entry;
        options.show(entry.context, entry.source as S);
        clearTimer(timer);
        timer = setTimer(endCurrent, Math.max(0, entry.duration));
        return;
      }
      // A live update for one still waiting: it takes that one's place in line.
      const at = waiting.findIndex((w) => w.key === entry.key);
      if (at !== -1) {
        entry.enqueuedAt = waiting[at].enqueuedAt;
        entry.timestamp = waiting[at].timestamp;
        waiting[at] = entry;
        pump();
        return;
      }
    }

    // Insert by timestamp, after every entry with the same or an earlier one,
    // so equal timestamps keep arrival order.
    let at = waiting.length;
    while (at > 0 && waiting[at - 1].timestamp > entry.timestamp) at--;
    waiting.splice(at, 0, entry);

    while (waiting.length > maxWaiting) {
      const oldest = waiting.shift() as Entry<C, S>;
      drop(oldest, 'overflow');
    }

    pump();
  }

  function clear(): void {
    cleared = true;
    if (timer !== null) clearTimer(timer);
    if (staleTimer !== null) clearTimer(staleTimer);
    timer = staleTimer = null;
    current = null;
    waiting.length = 0;
  }

  return { enqueue, size: () => waiting.length, clear };
}
