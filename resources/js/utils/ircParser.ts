/**
 * Twitch IRC v3 line parser.
 *
 * Pure functions, no WebSocket and no Vue, so the whole protocol surface is
 * testable without a network. `useTwitchChat` owns the socket and calls in here.
 *
 * The overlay reads chat DIRECTLY from Twitch over anonymous IRC-over-WebSocket
 * rather than having it relayed through Overlabels. That decision is in the
 * chat design notes; the short version is that chat is public, an anonymous
 * `justinfan` connection needs no credentials, and routing a firehose through
 * the server would cost real money to add latency.
 *
 * Everything here treats the wire as hostile. A chat line is a stranger's
 * keyboard: values are never trusted, never re-parsed, and the caller is
 * expected to escape before rendering.
 */

/** A parsed IRC line, before any Twitch-specific interpretation. */
export interface IrcLine {
  tags: Record<string, string>;
  /** Everything between `:` and the first space, e.g. `foo!foo@foo.tmi.twitch.tv`. */
  prefix: string | null;
  /** `PRIVMSG`, `CLEARCHAT`, `PING`, `001`, ... */
  command: string;
  /** Middle params, not including the trailing one. */
  params: string[];
  /** The final `:`-prefixed parameter, which is where message text lives. */
  trailing: string | null;
}

/** One chat message, normalised for the renderer's data slots. */
export interface ChatMessage {
  /** Twitch's message id. Stable, and what CLEARMSG deletes by. */
  id: string;
  /** Sender's numeric Twitch user id. What CLEARCHAT targets. */
  userId: string;
  /** Lowercase login. */
  login: string;
  /** Display name, which may differ in case or be a full localised name. */
  author: string;
  /** The raw message text. NOT escaped - the caller escapes at render. */
  text: string;
  /** Chosen name colour as `#rrggbb`, or empty when the chatter never set one. */
  color: string;
  /** Space-separated badge set names, e.g. `broadcaster moderator subscriber`. */
  badges: string;
  /** Unix SECONDS, honouring the project-wide timestamp contract. */
  at: number;
  isMod: boolean;
  isSubscriber: boolean;
  isVip: boolean;
  isBroadcaster: boolean;
  /** Twitch's own "first time chatting in this channel" flag. */
  isFirstMessage: boolean;
  /**
   * Empty for a native message. During a Shared Chat session, a message
   * duplicated in from another channel carries that channel's room id here, so
   * a template can mark it as foreign. `source-room-id` differing from
   * `room-id` is Twitch's own discriminator.
   */
  sourceRoomId: string;
  /**
   * Twitch emote positions, as character ranges into `text`.
   *
   * Shaped to match what `useEmoteParser` already consumes for alert payloads,
   * so chat reuses the alert emote pipeline rather than growing a second one.
   * Third-party emotes (7TV, BTTV, FFZ) are not in here - they carry no
   * positions on the wire and are matched by token further down.
   */
  emotes: TwitchEmotePosition[];
}

/** A Twitch emote occurrence: an inclusive character range into the message. */
export interface TwitchEmotePosition {
  begin: number;
  end: number;
  id: string;
}

/**
 * Parse the IRC `emotes` tag into positions.
 *
 * Wire format is `id:start-end,start-end/id:start-end`, where the ranges are
 * inclusive and count CODE POINTS rather than UTF-16 units - a message with an
 * astral-plane emoji before an emote shifts the indices. That mismatch is
 * handled where the ranges are applied, not here; this stays a literal reading
 * of the tag.
 *
 * Malformed segments are skipped rather than throwing. This runs inside a socket
 * handler on attacker-influenced input, so a surprise here must never be able to
 * take the overlay down.
 */
export function parseEmoteTag(raw: string): TwitchEmotePosition[] {
  if (!raw) return [];

  const positions: TwitchEmotePosition[] = [];

  for (const group of raw.split('/')) {
    const colon = group.indexOf(':');
    if (colon === -1) continue;

    const id = group.slice(0, colon);
    if (!id) continue;

    for (const range of group.slice(colon + 1).split(',')) {
      const dash = range.indexOf('-');
      if (dash === -1) continue;

      const begin = Number(range.slice(0, dash));
      const end = Number(range.slice(dash + 1));
      if (!Number.isInteger(begin) || !Number.isInteger(end) || begin < 0 || end < begin) continue;

      positions.push({ begin, end, id });
    }
  }

  return positions;
}

/**
 * Unescape an IRCv3 tag value.
 *
 * Tag values encode the characters that would otherwise break the tag list.
 * Getting this wrong is not cosmetic: a display name containing an escaped
 * semicolon would otherwise split into two bogus tags.
 */
function unescapeTagValue(value: string): string {
  let out = '';
  for (let i = 0; i < value.length; i++) {
    if (value[i] !== '\\') {
      out += value[i];
      continue;
    }
    const next = value[++i];
    if (next === 's') out += ' ';
    else if (next === ':') out += ';';
    else if (next === 'r') out += '\r';
    else if (next === 'n') out += '\n';
    else if (next === '\\') out += '\\';
    else if (next === undefined)
      break; // trailing lone backslash: drop it
    else out += next;
  }
  return out;
}

/** Parse the `@a=1;b=2` tag block into a plain object. */
export function parseTags(raw: string): Record<string, string> {
  const tags: Record<string, string> = {};
  if (!raw) return tags;

  for (const pair of raw.split(';')) {
    if (!pair) continue;
    const eq = pair.indexOf('=');
    // A valueless tag (`@foo`) is legal and means "present, empty".
    const key = eq === -1 ? pair : pair.slice(0, eq);
    const value = eq === -1 ? '' : pair.slice(eq + 1);
    if (key) tags[key] = unescapeTagValue(value);
  }

  return tags;
}

/**
 * Parse one raw IRC line. Returns null for an empty line.
 *
 * Deliberately tolerant: an unrecognised command still parses, so a future
 * Twitch addition shows up as an ignorable line rather than an exception inside
 * a socket handler.
 */
export function parseIrcLine(line: string): IrcLine | null {
  let rest = line.trim();
  if (!rest) return null;

  let tags: Record<string, string> = {};
  if (rest.startsWith('@')) {
    const sp = rest.indexOf(' ');
    if (sp === -1) return null;
    tags = parseTags(rest.slice(1, sp));
    rest = rest.slice(sp + 1);
  }

  let prefix: string | null = null;
  if (rest.startsWith(':')) {
    const sp = rest.indexOf(' ');
    if (sp === -1) return null;
    prefix = rest.slice(1, sp);
    rest = rest.slice(sp + 1);
  }

  // The trailing parameter starts at the first " :" and runs to end of line;
  // it is the only param allowed to contain spaces.
  let trailing: string | null = null;
  const trailingIdx = rest.indexOf(' :');
  if (rest.startsWith(':')) {
    trailing = rest.slice(1);
    rest = '';
  } else if (trailingIdx !== -1) {
    trailing = rest.slice(trailingIdx + 2);
    rest = rest.slice(0, trailingIdx);
  }

  const parts = rest.split(' ').filter(Boolean);
  const command = (parts.shift() ?? '').toUpperCase();
  if (!command) return null;

  return { tags, prefix, command, params: parts, trailing };
}

/** Pull the login out of a `nick!user@host` prefix. */
function loginFromPrefix(prefix: string | null): string {
  if (!prefix) return '';
  const bang = prefix.indexOf('!');
  return (bang === -1 ? prefix : prefix.slice(0, bang)).toLowerCase();
}

/**
 * Badge set names only, dropping the version.
 *
 * `badges=broadcaster/1,subscriber/12` becomes `broadcaster subscriber`, which
 * is what a template wants for styling (`.badge-subscriber`). The versions
 * matter only for rendering Twitch's badge artwork, which is a separate concern.
 */
function badgeNames(raw: string): string {
  if (!raw) return '';
  return raw
    .split(',')
    .map((b) => b.split('/')[0])
    .filter(Boolean)
    .join(' ');
}

/**
 * Turn a PRIVMSG line into a ChatMessage, or null if it is not one.
 *
 * `tmi-sent-ts` is milliseconds on the wire and is converted to SECONDS here, to
 * match the project's timestamp contract. Falls back to local time only when the
 * tag is absent, which should not happen on a tags-enabled connection.
 */
export function toChatMessage(line: IrcLine): ChatMessage | null {
  if (line.command !== 'PRIVMSG') return null;

  const t = line.tags;
  const login = loginFromPrefix(line.prefix);
  const badgesRaw = t['badges'] ?? '';

  // During Shared Chat, a message from another channel carries that channel's
  // badges under `source-badges`. Twitch's own UI shows the chatter as they
  // appear where they typed, which is the honest answer to "who is this".
  const sourceRoomId = t['source-room-id'] && t['source-room-id'] !== t['room-id'] ? t['source-room-id'] : '';
  const effectiveBadges = sourceRoomId && t['source-badges'] ? t['source-badges'] : badgesRaw;

  const tsMs = Number(t['tmi-sent-ts']);
  const at = Number.isFinite(tsMs) && tsMs > 0 ? Math.floor(tsMs / 1000) : Math.floor(Date.now() / 1000);

  const badges = badgeNames(effectiveBadges);
  const badgeSet = new Set(badges.split(' '));

  return {
    id: t['id'] ?? '',
    userId: t['user-id'] ?? '',
    login,
    author: t['display-name'] || login,
    text: line.trailing ?? '',
    color: t['color'] ?? '',
    badges,
    at,
    // Derived from the badges we are actually rendering, not just the `mod`
    // tag, so the flag and the badge can never disagree. Two cases need it:
    // the broadcaster is implicitly a moderator but has `mod=0`, and a
    // Shared Chat message carries its moderator standing only in
    // `source-badges`, with no `mod` tag for this room at all.
    isMod: t['mod'] === '1' || badgeSet.has('moderator') || badgeSet.has('broadcaster'),
    isSubscriber: t['subscriber'] === '1' || badgeSet.has('subscriber') || badgeSet.has('founder'),
    isVip: badgeSet.has('vip'),
    isBroadcaster: badgeSet.has('broadcaster'),
    isFirstMessage: t['first-msg'] === '1',
    sourceRoomId,
    emotes: parseEmoteTag(t['emotes'] ?? ''),
  };
}

/** What a moderation line asks the client to remove. */
export type ModerationAction =
  | { type: 'delete_message'; messageId: string }
  | { type: 'purge_user'; userId: string }
  | { type: 'purge_login'; login: string }
  | { type: 'clear_all' };

/**
 * Interpret CLEARMSG / CLEARCHAT.
 *
 * This is the one part of the chat feature that genuinely matters rather than
 * merely being nice: a message a moderator deleted must leave the overlay
 * immediately, or a slur that was removed from chat stays burned into the
 * stream and into every VOD and clip of it.
 *
 * CLEARCHAT carries a target login as its trailing parameter when a single user
 * is banned or timed out, and no trailing parameter when the whole chat is
 * cleared. `target-user-id` is preferred over the login because it survives a
 * rename between the message arriving and the ban landing.
 */
export function toModerationAction(line: IrcLine): ModerationAction | null {
  if (line.command === 'CLEARMSG') {
    const messageId = line.tags['target-msg-id'] ?? '';
    return messageId ? { type: 'delete_message', messageId } : null;
  }

  if (line.command === 'CLEARCHAT') {
    const userId = line.tags['target-user-id'] ?? '';
    if (userId) return { type: 'purge_user', userId };
    // Fall back to the login when the id tag is missing. Never treat a
    // named target as "clear everything": failing open here would wipe the
    // overlay on every timeout, and failing closed would leave a banned
    // chatter's messages on stream. Both are wrong, so handle the login.
    const login = (line.trailing ?? '').trim().toLowerCase();
    if (login) return { type: 'purge_login', login };

    return { type: 'clear_all' };
  }

  return null;
}
