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
const TWITCH_EMOTES = new Map<string, string>([
  ['Kappa', '25'],
  ['DansGame', '33'],
  ['Kreygasm', '41'],
  ['4Head', '354'],
  ['LUL', '425618'],
  ['WutFace', '28087'],
  ['NotLikeThis', '58765'],
  ['PogChamp', '305954156'],
]);

/**
 * Codes commonly present in 7TV/BTTV/FFZ channel sets. These exercise the
 * token-matching path rather than the position path; whether they resolve
 * depends on the channel's own sets, which is realistic.
 */
const THIRD_PARTY_CODES = new Set([
  'KEKW',
  'OMEGALUL',
  'Sadge',
  'Madge',
  'monkaS',
  'Pog',
  'PogU',
  'EZ',
  'Clap',
  'catJAM',
  'widepeepoHappy',
  'peepoHappy',
  'PepeLaugh',
  'D:',
]);

/**
 * Whole chat lines, each written the way one person would type it.
 *
 * This used to be a phrase with an emote glued on either side at random, which
 * produced "first time here Sadge" and "this is the best stream Madge": every
 * line read like a fixture. An emote belongs to the sentence it reacts to, so it
 * is written into the line rather than rolled for.
 *
 * Nothing here is tagged by hand. Each line is filed at module load by the
 * codes it contains (Twitch emote, third-party code, or neither), so the
 * `emoteChance` / `thirdPartyChance` knobs keep meaning what they say and a new
 * line cannot be filed under the wrong one.
 *
 * Mostly short and lower case, because chat is. Genre-neutral, because the
 * preview runs for a speedrunner and a DJ alike. `{name}` is another chatter
 * from the same pool, so replies point at someone who is actually talking.
 */
const LINES = [
  // Reactions
  'gg',
  'gg wp',
  'no way',
  'NO WAY',
  'wait what',
  'wait go back',
  '??',
  '?',
  'lmao',
  'lol',
  'HAHAHA',
  'W',
  'L',
  'o7',
  'F',
  'rip',
  'clean',
  'that was clean',
  'so close',
  'so close omg',
  'unlucky',
  'true',
  'real',
  'called it',
  'i literally said that 2 minutes ago',
  'the timing on that',
  'ok that was actually impressive',
  'i would have panicked',
  'bold strategy',
  'skill issue',
  'plot twist',
  'not the ending i expected',
  'clip that',
  'someone clip that',
  'that one is going in the highlights',
  'i need that on a shirt',
  'we are so back',
  'its so over',
  // Backseating and cheering
  'one more try',
  'you got this',
  'believe',
  'trust the process',
  'save first',
  'did it save?',
  'left. LEFT',
  'behind you',
  'how many tries is this',
  'new pb?',
  'no spoilers pls',
  // Chatting
  'hi chat',
  'evening all',
  'just got here, what did i miss',
  'how long have you been live?',
  'brb food',
  'back',
  'wb',
  'eating dinner and watching, perfect',
  'this is so cozy',
  'the vibes today',
  'chat is moving fast today',
  'how is it already this late',
  '5 more minutes then bed',
  'this song goes hard',
  'whats the song?',
  'audio is a bit quiet btw',
  'mic is fine now',
  'hydrate 💧',
  'pretty 😍',
  'chat behave',
  '!uptime',
  '!discord',
  '!followage',
  // Replies
  '@{name} same',
  '@{name} lol true',
  '@{name} welcome back',
  '@{name} hi!',
  '@{name} nah youre right',
  '@{name} i was thinking the same thing',
  '@{name} its in the panels below',
  '@{name} LUL',
  '@{name} KEKW',
  // Twitch emotes, written where a person would put them
  'LUL',
  'LUL LUL',
  'LUL LUL LUL',
  'the timing LUL',
  'chat called it LUL',
  'that was planned Kappa',
  'totally on purpose Kappa',
  'PogChamp',
  'PogChamp PogChamp',
  'no way PogChamp',
  'NotLikeThis',
  'NotLikeThis NotLikeThis',
  'not again NotLikeThis',
  'WutFace',
  'what was that WutFace',
  'jumpscare WutFace',
  'Kreygasm',
  'this song Kreygasm',
  'that sound design Kreygasm',
  'DansGame',
  'the camera angle DansGame',
  'just win 4Head',
  'just dont get hit 4Head',
  // Third-party codes
  'KEKW',
  'KEKW KEKW',
  'OMEGALUL',
  'no shot KEKW',
  'chat is cooked KEKW',
  'EZ',
  'EZ Clap',
  'too easy EZ',
  'Clap Clap Clap',
  'Pog',
  'PogU',
  'wait thats actually Pog',
  'monkaS',
  'monkaS monkaS',
  'i cant watch monkaS',
  'dont go in there monkaS',
  'Sadge',
  'missed the start Sadge',
  'one hp Sadge',
  'Madge',
  'who did that Madge',
  'catJAM',
  'catJAM catJAM catJAM',
  'this beat catJAM',
  'widepeepoHappy',
  'hi chat widepeepoHappy',
  'cozy stream peepoHappy',
  'PepeLaugh',
  'PepeLaugh here it comes',
  'PepeLaugh they dont know',
  'D:',
  'D: D:',
];

/**
 * What someone says the first time they chat in a channel.
 *
 * Kept apart from LINES because Twitch flags that message (`first-msg=1`) and
 * the overlay can style it, so it has to read like an arrival. Emote-free, so
 * the first-message chip shows on a greeting rather than on a reaction.
 */
const FIRST_LINES = [
  'hi! first time catching a stream',
  'found you through a raid, hi everyone',
  'hello! just followed',
  'hey, first time chatting',
  'been watching for a while, finally saying hi',
  'hello from brazil',
  'hi from finland',
  'the algorithm sent me here, no regrets',
];

/**
 * Lines a whole chat repeats at once when something happens on stream.
 *
 * A wave is the most recognisable thing a real chat does and the one thing a
 * stream of independent random lines can never produce: one moment, then ten
 * people posting the same emote within seconds.
 */
const WAVE_LINES = [
  'LUL',
  'KEKW',
  'OMEGALUL',
  'o7',
  'W',
  'gg',
  'F',
  '?',
  'Pog',
  'PogChamp',
  'EZ Clap',
  'NotLikeThis',
  'monkaS',
  'D:',
  'catJAM',
  'LMAO',
];

function codesIn(line: string): { twitch: boolean; thirdParty: boolean } {
  const words = line.split(' ');

  return {
    twitch: words.some((word) => TWITCH_EMOTES.has(word)),
    thirdParty: words.some((word) => THIRD_PARTY_CODES.has(word)),
  };
}

const TWITCH_LINES = LINES.filter((line) => codesIn(line).twitch);
const THIRD_PARTY_LINES = LINES.filter((line) => !codesIn(line).twitch && codesIn(line).thirdParty);
const PLAIN_LINES = LINES.filter((line) => !codesIn(line).twitch && !codesIn(line).thirdParty);

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

/** Pick a line for one message, by the same knobs the old phrase-and-emote roll used. */
function sampleLine(options: ChatSampleOptions, first: boolean): string {
  if (first) return pick(FIRST_LINES);
  if (chance(options.emoteChance)) return pick(TWITCH_LINES);
  if (chance(options.thirdPartyChance)) return pick(THIRD_PARTY_LINES);

  return pick(PLAIN_LINES);
}

/**
 * The IRC `emotes` tag for a finished line: `id:begin-end,begin-end/id:...`.
 *
 * Computed from the text rather than tracked while building it, because the
 * renderer slices the string by these indices and getting them wrong would mean
 * debugging the fixture instead of the overlay. Counted in CODE POINTS, as
 * Twitch does, so an emoji earlier in the line does not shift the ranges.
 */
function emoteTag(text: string): string {
  const ranges = new Map<string, string[]>();
  let cursor = 0;

  for (const word of text.split(' ')) {
    const length = Array.from(word).length;
    const id = TWITCH_EMOTES.get(word);
    if (id) {
      ranges.set(id, [...(ranges.get(id) ?? []), `${cursor}-${cursor + length - 1}`]);
    }
    cursor += length + 1; // the joining space
  }

  return Array.from(ranges, ([id, list]) => `${id}:${list.join(',')}`).join('/');
}

/**
 * Build one PRIVMSG line, tags and all.
 *
 * `text` overrides the sampled line; the preview feed uses it to make a chat
 * wave. A first message always gets a greeting, whatever `text` says, because
 * the overlay styles it as an arrival.
 */
export function privmsgLine(options: ChatSampleOptions, seed = messageCounter++, text?: string): string {
  const n = seed % options.chatters;
  const login = `chatter${n}`;
  const display = `Chatter${n}`;

  const first = chance(options.firstChance);
  const line = (first || text === undefined ? sampleLine(options, first) : text).replace(
    '{name}',
    () => `Chatter${(n + 1 + Math.floor(Math.random() * (options.chatters - 1))) % options.chatters}`,
  );

  const badges = pick(BADGE_SETS);
  const foreign = chance(options.foreignChance);

  const tags = [
    `badge-info=`,
    `badges=${badges}`,
    `color=${pick(COLORS)}`,
    `display-name=${display}`,
    `emotes=${emoteTag(line)}`,
    `first-msg=${first ? '1' : '0'}`,
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

  return `@${tags.join(';')} :${login}!${login}@${login}.tmi.twitch.tv PRIVMSG #${options.channel} :${line}`;
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

/** Per message, when no wave is running. About one every twenty messages. */
const WAVE_CHANCE = 0.05;

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
 *
 * Now and then a wave starts: for the next several messages, most chatters
 * repeat the same reaction, sometimes twice over. Preview only - the dev hose
 * keeps independent lines, because a load test wants an even spread.
 */
export function createChatSampleFeed(sink: ChatSink, over: Partial<ChatSampleOptions> = {}): ChatSampleFeed {
  const options: ChatSampleOptions = { ...CHAT_SAMPLE_DEFAULTS, ...FEED_FLAVOUR, ...over };
  let timer: ReturnType<typeof setInterval> | null = null;
  let wave = { line: '', left: 0 };

  function nextText(): string | undefined {
    if (wave.left === 0 && chance(WAVE_CHANCE)) {
      wave = { line: pick(WAVE_LINES), left: 4 + Math.floor(Math.random() * 7) };
    }
    if (wave.left === 0) return undefined;

    wave.left--;
    if (!chance(0.75)) return undefined;

    return chance(0.25) ? `${wave.line} ${wave.line}` : wave.line;
  }

  function emit(): void {
    sink.injectRawLine(privmsgLine(options, Math.floor(Math.random() * options.chatters), nextText()));
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
