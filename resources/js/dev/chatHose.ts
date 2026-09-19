/**
 * Synthetic chat firehose. DEVELOPMENT TOOL - never ships.
 *
 * Installed only when the build was made with `VITE_CHAT_HOSE=1`. That check is
 * inlined by Vite at build time, so an ordinary production build eliminates the
 * import site and this module never enters the graph. There is no runtime flag
 * and nothing to leave switched on by accident.
 *
 * The lines themselves come from `utils/chatSample.ts`, which DOES ship - the
 * chat designer previews a look with the same fixtures at a human rate. What
 * stays here is the load-testing driver: the rate dial, the moderation roll,
 * the frame sampler and the console handle. None of that is product code.
 *
 * WHY FAKE RATHER THAN POINT AT A BUSY CHANNEL:
 *
 * - Reproducible. A real channel's rate swings minute to minute, so you cannot
 *   re-run the same test after a change. This is a dial.
 * - Available at 3am, with no dependency on someone big being live.
 * - Harder than reality on demand. A real 86k-viewer sample measured ~134
 *   messages per MINUTE. The interesting question is where the ceiling is, not
 *   whether it survives a trickle.
 * - Deletions on demand. CLEARMSG and CLEARCHAT are the fiddliest paths and are
 *   near-impossible to trigger deliberately in someone else's chat.
 * - Nothing touches Overlabels' server, because chat never does anyway: the
 *   overlay reads Twitch directly. This measures the renderer, which is the
 *   only thing chat volume actually loads.
 *
 * Lines are fed through `useTwitchChat.injectRawLine()`, i.e. the real IRC
 * parser, so what you are testing is the genuine pipeline.
 *
 *   __olChatHose.start({ rate: 50 })
 *   __olChatHose.burst(500)
 *   __olChatHose.stop()          // prints a report
 */

import { CHAT_SAMPLE_DEFAULTS, type ChatSampleOptions, clearchatLine, clearmsgLine, privmsgLine } from '@/utils/chatSample';

export interface ChatHoseOptions extends ChatSampleOptions {
  /** Messages per second. */
  rate: number;
  /** Probability per second that a moderation action fires. */
  moderationChance: number;
}

const DEFAULTS: ChatHoseOptions = {
  ...CHAT_SAMPLE_DEFAULTS,
  channel: 'loadtest',
  rate: 20,
  moderationChance: 0.05,
};

interface ChatSink {
  injectRawLine(raw: string): void;
}

function pick<T>(list: readonly T[]): T {
  return list[Math.floor(Math.random() * list.length)];
}

function chance(p: number): boolean {
  return Math.random() < p;
}

interface FrameSampler {
  stop(): { frames: number; seconds: number; avgFps: number; worstFrameMs: number; longFrames: number };
}

/**
 * Sample frame timing while the hose runs.
 *
 * The number worth having is not "did it keep up" but "did it drop frames" -
 * an overlay that stutters mid-stream is the actual failure mode. `longFrames`
 * counts frames over 50ms, which is where stutter becomes visible.
 */
function sampleFrames(): FrameSampler {
  const start = performance.now();
  let last = start;
  let frames = 0;
  let worstFrameMs = 0;
  let longFrames = 0;
  let running = true;

  const tick = (now: number) => {
    if (!running) return;
    const delta = now - last;
    last = now;
    frames++;
    if (delta > worstFrameMs) worstFrameMs = delta;
    if (delta > 50) longFrames++;
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);

  return {
    stop() {
      running = false;
      const seconds = (performance.now() - start) / 1000;

      return {
        frames,
        seconds: Number(seconds.toFixed(1)),
        avgFps: Number((frames / seconds).toFixed(1)),
        worstFrameMs: Number(worstFrameMs.toFixed(1)),
        longFrames,
      };
    },
  };
}

export interface ChatHose {
  start(options?: Partial<ChatHoseOptions>): void;
  stop(): void;
  burst(count: number, options?: Partial<ChatHoseOptions>): void;
}

export function createChatHose(sink: ChatSink): ChatHose {
  let timer: ReturnType<typeof setInterval> | null = null;
  let sampler: FrameSampler | null = null;
  let sent = 0;
  let startedAt = 0;
  let config: ChatHoseOptions = { ...DEFAULTS };

  // Emitted in batches on a 100ms tick rather than one timer per message: at
  // high rates the timers themselves would dominate the measurement.
  const TICK_MS = 100;

  const recentIds: string[] = [];

  function emitOne(): void {
    const line = privmsgLine(config);
    const id = /id=([0-9a-f-]+)/.exec(line)?.[1];
    if (id) {
      recentIds.push(id);
      if (recentIds.length > 200) recentIds.shift();
    }
    sink.injectRawLine(line);
    sent++;
  }

  function maybeModerate(): void {
    if (!chance((config.moderationChance * TICK_MS) / 1000)) return;

    const roll = Math.random();
    if (roll < 0.7 && recentIds.length) {
      sink.injectRawLine(clearmsgLine(config, pick(recentIds)));
    } else if (roll < 0.95) {
      sink.injectRawLine(clearchatLine(config, `chatter${Math.floor(Math.random() * config.chatters)}`));
    } else {
      sink.injectRawLine(clearchatLine(config));
    }
  }

  function start(options: Partial<ChatHoseOptions> = {}): void {
    stop();
    config = { ...DEFAULTS, ...options };
    sent = 0;
    startedAt = performance.now();
    sampler = sampleFrames();

    const perTick = Math.max(1, Math.round((config.rate * TICK_MS) / 1000));

    timer = setInterval(() => {
      for (let i = 0; i < perTick; i++) emitOne();
      maybeModerate();
    }, TICK_MS);

    console.info(`[chat hose] running at ~${config.rate}/s (${perTick} per ${TICK_MS}ms tick). __olChatHose.stop() to finish.`);
  }

  function stop(): void {
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
    if (!sampler) return;

    const frames = sampler.stop();
    sampler = null;

    // `seconds` comes from the frame sampler rather than being duplicated here;
    // the two measure the same window and having both would just overwrite.
    const seconds = (performance.now() - startedAt) / 1000;
    console.info('[chat hose] report', {
      sent,
      actualRatePerSecond: Number((sent / seconds).toFixed(1)),
      ...frames,
    });
  }

  function burst(count: number, options: Partial<ChatHoseOptions> = {}): void {
    const previous = config;
    config = { ...DEFAULTS, ...options };
    for (let i = 0; i < count; i++) emitOne();
    config = previous;
    console.info(`[chat hose] burst of ${count} delivered`);
  }

  return { start, stop, burst };
}

declare global {
  interface Window {
    __olChatHose?: ChatHose;
  }
}

export function installChatHose(sink: ChatSink): void {
  window.__olChatHose = createChatHose(sink);
  console.info('[chat hose] installed. __olChatHose.start({ rate: 50 })');
}
