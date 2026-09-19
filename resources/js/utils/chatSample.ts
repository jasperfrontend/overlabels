/**
 * Synthetic Twitch IRC line synthesis.
 *
 * Ships in production. This is the fixture half of what used to be
 * `dev/chatHose.ts` alone: the part that builds one plausible tagged IRC line.
 * The dev hose still drives it in a loop for load testing, and the chat
 * designer drives it at a human rate to show a look while it is being chosen.
 *
 * Lines from here are meant to go in through `useTwitchChat.injectRawLine()`,
 * i.e. the real parser. Anything that feeds the renderer some other way is
 * measuring or previewing a pipeline nobody actually runs.
 *
 * WHY SYNTHESIZED CHAT RATHER THAN THE STREAMER'S OWN:
 *
 * - A streamer with no chat, or one who is not live, must never be shown an
 *   empty box and told this is what they get.
 * - Reproducible. A real channel's rate swings minute to minute, so a look
 *   cannot be compared before and after a change, and a load test cannot be
 *   re-run.
 * - Deletions on demand. CLEARMSG and CLEARCHAT are the fiddliest paths and
 *   are near-impossible to trigger deliberately in someone else's chat.
 * - Nothing touches Overlabels' server, because chat never does anyway: the
 *   overlay reads Twitch directly.
 */

/**
 * Real global Twitch emote ids, so images actually load and cost real bytes.
 *
 * Every id here must return 200 from the CDN. A dead one still costs a request
 * but never paints, which quietly understates the render load - exactly the
 * kind of fixture rot that makes a load test lie in the flattering direction,
 * and leaves a gap in the preview where the streamer expects an emote.
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

export interface ChatSampleOptions {
  /** Probability a message carries Twitch emotes (position-based path). */
  emoteChance: number;
  /** Probability a message carries third-party emote codes (token path). */
  thirdPartyChance: number;
  /** Probability a message is flagged as the chatter's first ever. */
  firstChance: number;
  /** Size of the synthetic chatter pool. Drives unique-chatter behaviour. */
  chatters: number;
  /** Channel name in the PRIVMSG target. Cosmetic. */
  channel: string;
  /** room-id tag. Only matters if you are testing Shared Chat. */
  roomId: string;
  /** Probability a message is a Shared Chat message from another channel. */
  foreignChance: number;
}

export const CHAT_SAMPLE_DEFAULTS: ChatSampleOptions = {
  emoteChance: 0.35,
  thirdPartyChance: 0.35,
  firstChance: 0.02,
  chatters: 400,
  channel: 'sample',
  roomId: '1',
  foreignChance: 0,
};

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
 * would mean debugging the fixture instead of the overlay.
 */
export function privmsgLine(options: ChatSampleOptions, seed = messageCounter++): string {
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
    `first-msg=${chance(options.firstChance) ? '1' : '0'}`,
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
export function clearmsgLine(options: ChatSampleOptions, targetId: string): string {
  return `@login=someone;room-id=${options.roomId};target-msg-id=${targetId} :tmi.twitch.tv CLEARMSG #${options.channel} :gone`;
}

/** CLEARCHAT with a target purges one user; without one it clears the room. */
export function clearchatLine(options: ChatSampleOptions, login?: string): string {
  if (!login) {
    return `@room-id=${options.roomId} :tmi.twitch.tv CLEARCHAT #${options.channel}`;
  }

  return `@ban-duration=600;room-id=${options.roomId};target-user-id=${1000 + Number(login.replace(/\D/g, '') || 0)} :tmi.twitch.tv CLEARCHAT #${options.channel} :${login}`;
}

interface ChatSink {
  injectRawLine(raw: string): void;
}

export interface ChatSampleFeed {
  /** Messages per minute. 0 stops the feed and leaves the window as it is. */
  setRate(perMinute: number): void;
  /** Drop `count` messages in at once, ignoring the rate. */
  burst(count: number): void;
  /** Empty the window, the way a moderator clearing chat would. */
  clear(): void;
  /** Stop the timer. Nothing else holds a resource. */
  stop(): void;
}

/**
 * What a preview feed wants that a load test does not.
 *
 * A small pool, because sequential `Chatter0, Chatter1, Chatter2` reads as a
 * list rather than as a conversation; twenty-four names repeat the way a real
 * chat does. And a first-message rate two orders up from reality, because the
 * accent-coloured "first message" chip is one of the things being chosen and
 * at 2% it would not appear once while someone picked a colour.
 */
const FEED_FLAVOUR: Partial<ChatSampleOptions> = {
  chatters: 24,
  firstChance: 0.12,
};

/**
 * A human-paced sample feed, for showing a look while it is being chosen.
 *
 * Deliberately NOT the dev hose: no frame sampler, no batching, no console
 * handle, and the rate is per MINUTE because the question here is "does this
 * read well", not "where does it break". A real 86k-viewer channel measured
 * ~134 messages a minute, so that is the top of the useful range rather than
 * the bottom.
 *
 * Seeds are random rather than the module counter's running total: the counter
 * walks the chatter pool in order, which is exactly right for spreading load
 * across distinct users and exactly wrong for looking like people talking.
 *
 * One timer, rescheduled on every rate change, so a slider drag does not pile
 * up intervals.
 */
export function createChatSampleFeed(sink: ChatSink, over: Partial<ChatSampleOptions> = {}): ChatSampleFeed {
  const options: ChatSampleOptions = { ...CHAT_SAMPLE_DEFAULTS, ...FEED_FLAVOUR, ...over };
  let timer: ReturnType<typeof setInterval> | null = null;

  function emit(): void {
    sink.injectRawLine(privmsgLine(options, Math.floor(Math.random() * options.chatters)));
  }

  function stop(): void {
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  }

  return {
    setRate(perMinute: number) {
      stop();
      if (!Number.isFinite(perMinute) || perMinute <= 0) return;

      // Floored at 150ms so a slider pushed to the top cannot turn the preview
      // into a load test on someone's laptop.
      timer = setInterval(emit, Math.max(150, 60_000 / perMinute));
    },
    burst(count: number) {
      for (let i = 0; i < count; i++) emit();
    },
    clear() {
      sink.injectRawLine(clearchatLine(options));
    },
    stop,
  };
}
