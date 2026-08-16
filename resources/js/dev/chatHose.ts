/**
 * Synthetic chat firehose. DEVELOPMENT TOOL - never ships.
 *
 * Installed only when the build was made with `VITE_CHAT_HOSE=1`. That check is
 * inlined by Vite at build time, so an ordinary production build eliminates the
 * import site and this module never enters the graph. There is no runtime flag
 * and nothing to leave switched on by accident.
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

/**
 * Real global Twitch emote ids, so images actually load and cost real bytes.
 *
 * Every id here must return 200 from the CDN. A dead one still costs a request
 * but never paints, which quietly understates the render load - exactly the
 * kind of fixture rot that makes a load test lie in the flattering direction.
 *
 * This list WILL rot: Twitch retires global emotes. `BibleThump` (86) was here
 * until Twitch dropped it, and it 404s. Re-check before trusting a run whose
 * numbers look suspiciously good.
 *
 * Note it may still render in a real overlay - FFZ carries BibleThump as a
 * global - but that resolves through the token path, whereas anything listed
 * here goes out as an emote POSITION and is therefore fetched from Twitch's CDN
 * regardless of what the third-party sets have.
 *
 *   curl -o /dev/null -w "%{http_code}" \
 *     https://static-cdn.jtvnw.net/emoticons/v2/<id>/default/dark/1.0
 */
const TWITCH_EMOTES: Array<[code: string, id: string]> = [
  ['Kappa', '25'],
  ['DansGame', '33'],
  ['Kreygasm', '41'],
  ['4Head', '354'],
  ['LUL', '425618'],
  ['WutFace', '28087'],
  ['NotLikeThis', '58765'],
  ['PogChamp', '305954156'],
];

/**
 * Codes commonly present in 7TV/BTTV/FFZ channel sets. These exercise the
 * token-matching path rather than the position path; whether they resolve
 * depends on the channel's own sets, which is realistic.
 */
const THIRD_PARTY_CODES = ['Sadge', 'Madge', 'Pepega', 'monkaS', 'KEKW', 'Pog', 'widepeepoHappy', 'catJAM'];

const WORDS = [
  'hello',
  'chat',
  'that was insane',
  'gg',
  'no way',
  'first time here',
  'lets go',
  'what happened',
  'nice one',
  'o7',
  'this is the best stream',
  'im crying',
  'clip it',
];

const BADGE_SETS = ['', 'subscriber/12', 'moderator/1', 'vip/1', 'subscriber/3,moderator/1', 'broadcaster/1,subscriber/12'];

const COLORS = ['#1E90FF', '#FF69B4', '#00FF7F', '#FF4500', '#9146FF', ''];

export interface ChatHoseOptions {
  /** Messages per second. */
  rate: number;
  /** Probability a message carries Twitch emotes (position-based path). */
  emoteChance: number;
  /** Probability a message carries third-party emote codes (token path). */
  thirdPartyChance: number;
  /** Probability per second that a moderation action fires. */
  moderationChance: number;
  /** Size of the synthetic chatter pool. Drives unique-chatter behaviour. */
  chatters: number;
  /** Channel name in the PRIVMSG target. Cosmetic. */
  channel: string;
  /** room-id tag. Only matters if you are testing Shared Chat. */
  roomId: string;
  /** Probability a message is a Shared Chat message from another channel. */
  foreignChance: number;
}

const DEFAULTS: ChatHoseOptions = {
  rate: 20,
  emoteChance: 0.35,
  thirdPartyChance: 0.35,
  moderationChance: 0.05,
  chatters: 400,
  channel: 'loadtest',
  roomId: '1',
  foreignChance: 0,
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

let messageCounter = 0;

/**
 * Build one PRIVMSG line, tags and all.
 *
 * Emote positions are computed against the assembled text rather than guessed,
 * because the renderer slices the string by those indices. Getting them wrong
 * would mean debugging the hose instead of the overlay.
 */
export function privmsgLine(options: ChatHoseOptions, seed = messageCounter++): string {
  const n = seed % options.chatters;
  const login = `chatter${n}`;
  const display = `Chatter${n}`;

  const parts: string[] = [];
  const emotePositions: string[] = [];
  let cursor = 0;

  const push = (chunk: string) => {
    if (parts.length > 0) {
      cursor += 1; // the joining space
    }
    parts.push(chunk);
    const begin = cursor;
    cursor += chunk.length;
    return { begin, end: cursor - 1 };
  };

  if (chance(options.emoteChance)) {
    const [code, id] = pick(TWITCH_EMOTES);
    const { begin, end } = push(code);
    emotePositions.push(`${id}:${begin}-${end}`);
  }

  push(pick(WORDS));

  if (chance(options.thirdPartyChance)) {
    push(pick(THIRD_PARTY_CODES));
  }

  if (chance(options.emoteChance)) {
    const [code, id] = pick(TWITCH_EMOTES);
    const { begin, end } = push(code);
    emotePositions.push(`${id}:${begin}-${end}`);
  }

  const text = parts.join(' ');
  const badges = pick(BADGE_SETS);
  const foreign = chance(options.foreignChance);

  const tags = [
    `badge-info=`,
    `badges=${badges}`,
    `color=${pick(COLORS)}`,
    `display-name=${display}`,
    `emotes=${emotePositions.join('/')}`,
    `first-msg=${chance(0.02) ? '1' : '0'}`,
    `id=${crypto.randomUUID()}`,
    `mod=${badges.includes('moderator') ? '1' : '0'}`,
    `room-id=${options.roomId}`,
    `subscriber=${badges.includes('subscriber') ? '1' : '0'}`,
    `tmi-sent-ts=${Date.now()}`,
    `user-id=${1000 + n}`,
  ];

  if (foreign) {
    // source-room-id differing from room-id is Twitch's own discriminator.
    tags.push('source-room-id=999999', `source-badges=${badges}`);
  }

  return `@${tags.join(';')} :${login}!${login}@${login}.tmi.twitch.tv PRIVMSG #${options.channel} :${text}`;
}

/** CLEARMSG removes exactly one message. */
export function clearmsgLine(options: ChatHoseOptions, targetId: string): string {
  return `@login=someone;room-id=${options.roomId};target-msg-id=${targetId} :tmi.twitch.tv CLEARMSG #${options.channel} :gone`;
}

/** CLEARCHAT with a target purges one user; without one it clears the room. */
export function clearchatLine(options: ChatHoseOptions, login?: string): string {
  if (!login) {
    return `@room-id=${options.roomId} :tmi.twitch.tv CLEARCHAT #${options.channel}`;
  }

  return `@ban-duration=600;room-id=${options.roomId};target-user-id=${1000 + Number(login.replace(/\D/g, '') || 0)} :tmi.twitch.tv CLEARCHAT #${options.channel} :${login}`;
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
