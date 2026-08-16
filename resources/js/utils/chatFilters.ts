import type { ChatMessage } from '@/utils/ircParser';

/**
 * Display filters for the chat overlay.
 *
 * These decide what the overlay DRAWS and nothing else. The overlay reads chat
 * straight from Twitch over an anonymous connection, so there is nothing here
 * that touches Twitch, the chatter, or anyone else's view of the channel. A
 * hidden message is hidden on this overlay; it is still in chat, still in the
 * VOD, and still visible to every viewer and moderator.
 *
 * Moderation is a separate mechanism and is not optional: CLEARMSG and
 * CLEARCHAT are honoured in `useTwitchChat` regardless of anything here.
 */
export interface ChatFilters {
  /** Hide messages that start with `!`. */
  hideCommands: boolean;
  /** Lowercased logins whose messages are not drawn. */
  hiddenLogins: string[];
}

export const EMPTY_CHAT_FILTERS: ChatFilters = {
  hideCommands: false,
  hiddenLogins: [],
};

/**
 * Normalise whatever the render payload supplied into a usable filter set.
 *
 * Defensive because this arrives over the wire: a stale overlay, a partial
 * payload or an older app version must degrade to "show everything" rather
 * than throw inside the socket handler and take the feed down with it.
 */
export function toChatFilters(raw: unknown): ChatFilters {
  if (!raw || typeof raw !== 'object') return EMPTY_CHAT_FILTERS;

  const source = raw as { hide_commands?: unknown; hidden_logins?: unknown };

  const hiddenLogins = Array.isArray(source.hidden_logins)
    ? source.hidden_logins
        .filter((login): login is string => typeof login === 'string')
        .map((login) => login.trim().toLowerCase())
        .filter((login) => login !== '')
    : [];

  return {
    hideCommands: source.hide_commands === true,
    hiddenLogins,
  };
}

/** Is this filter set doing anything at all? Lets callers skip the work. */
export function hasActiveFilters(filters: ChatFilters): boolean {
  return filters.hideCommands || filters.hiddenLogins.length > 0;
}

/**
 * Should this message be kept out of the overlay?
 *
 * Applies to Shared Chat messages exactly as it does to native ones. A login
 * the streamer has hidden is hidden wherever they typed it from, which is the
 * only reading that is not surprising.
 */
export function shouldHideMessage(message: ChatMessage, filters: ChatFilters): boolean {
  if (filters.hideCommands && isCommand(message.text)) return true;

  if (filters.hiddenLogins.length > 0) {
    const login = (message.login ?? '').toLowerCase();
    if (login !== '' && filters.hiddenLogins.includes(login)) return true;
  }

  return false;
}

/**
 * A message "starting with !", taken literally, which is what the setting
 * promises.
 *
 * Leading whitespace is ignored so a stray space does not defeat it. This does
 * catch a chatter typing "!!!" or "!what a play" - they are not commands, but
 * narrowing it to `!` followed by a word character would still catch the
 * second one, and inventing a cleverer rule would make the toggle's label a
 * lie. Anyone who wants those shown leaves the toggle off.
 */
function isCommand(text: string): boolean {
  return text.trimStart().startsWith('!');
}
