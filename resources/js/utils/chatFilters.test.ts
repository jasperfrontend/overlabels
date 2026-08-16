import { describe, expect, it } from 'vitest';
import { EMPTY_CHAT_FILTERS, hasActiveFilters, shouldHideMessage, toChatFilters } from './chatFilters';
import type { ChatMessage } from './ircParser';

function msg(over: Partial<ChatMessage> = {}): ChatMessage {
  return {
    id: 'm1',
    userId: '1',
    login: 'ana',
    author: 'Ana',
    text: 'hello',
    color: '#ff0000',
    badges: 'subscriber',
    at: 1755273600,
    isMod: false,
    isSubscriber: true,
    isVip: false,
    isBroadcaster: false,
    isFirstMessage: false,
    sourceRoomId: '',
    badgeVersions: [],
    emotes: [],
    ...over,
  };
}

describe('toChatFilters', () => {
  it('reads the payload shape the server sends', () => {
    expect(toChatFilters({ hide_commands: true, hidden_logins: ['bot'] })).toEqual({
      hideCommands: true,
      hiddenLogins: ['bot'],
    });
  });

  it('falls back to showing everything when the payload is missing or malformed', () => {
    // Arrives over the wire from a possibly-older app, so it must degrade to
    // "show everything" rather than throw inside the socket handler.
    expect(toChatFilters(undefined)).toEqual(EMPTY_CHAT_FILTERS);
    expect(toChatFilters(null)).toEqual(EMPTY_CHAT_FILTERS);
    expect(toChatFilters('nonsense')).toEqual(EMPTY_CHAT_FILTERS);
    expect(toChatFilters({})).toEqual(EMPTY_CHAT_FILTERS);
  });

  it('treats anything other than true as false', () => {
    expect(toChatFilters({ hide_commands: 'yes' }).hideCommands).toBe(false);
    expect(toChatFilters({ hide_commands: 1 }).hideCommands).toBe(false);
  });

  it('normalises logins and drops non-strings', () => {
    const filters = toChatFilters({ hidden_logins: ['  BoT  ', 42, null, '', 'other'] });

    expect(filters.hiddenLogins).toEqual(['bot', 'other']);
  });

  it('survives hidden_logins arriving as something other than an array', () => {
    expect(toChatFilters({ hidden_logins: 'bot' }).hiddenLogins).toEqual([]);
  });
});

describe('hasActiveFilters', () => {
  it('is false for the default set', () => {
    expect(hasActiveFilters(EMPTY_CHAT_FILTERS)).toBe(false);
  });

  it('is true when either filter is doing something', () => {
    expect(hasActiveFilters({ hideCommands: true, hiddenLogins: [] })).toBe(true);
    expect(hasActiveFilters({ hideCommands: false, hiddenLogins: ['bot'] })).toBe(true);
  });
});

describe('shouldHideMessage', () => {
  it('hides nothing by default', () => {
    expect(shouldHideMessage(msg({ text: '!ping' }), EMPTY_CHAT_FILTERS)).toBe(false);
  });

  describe('hideCommands', () => {
    const filters = { hideCommands: true, hiddenLogins: [] };

    it('hides a message starting with !', () => {
      expect(shouldHideMessage(msg({ text: '!ping' }), filters)).toBe(true);
    });

    it('ignores leading whitespace so a stray space does not defeat it', () => {
      expect(shouldHideMessage(msg({ text: '   !ping' }), filters)).toBe(true);
    });

    it('keeps a message with ! anywhere but the start', () => {
      expect(shouldHideMessage(msg({ text: 'what a play!' }), filters)).toBe(false);
      expect(shouldHideMessage(msg({ text: 'nice !ping there' }), filters)).toBe(false);
    });

    it('hides !!! too, which the setting copy warns about', () => {
      // Not a command, but "starts with !" is what the toggle promises and a
      // cleverer rule would make the label a lie.
      expect(shouldHideMessage(msg({ text: '!!!' }), filters)).toBe(true);
    });

    it('keeps an empty message', () => {
      expect(shouldHideMessage(msg({ text: '' }), filters)).toBe(false);
    });
  });

  describe('hiddenLogins', () => {
    const filters = { hideCommands: false, hiddenLogins: ['spambot'] };

    it('hides a listed login', () => {
      expect(shouldHideMessage(msg({ login: 'spambot' }), filters)).toBe(true);
    });

    it('matches regardless of case', () => {
      expect(shouldHideMessage(msg({ login: 'SpamBot' }), filters)).toBe(true);
    });

    it('leaves everyone else alone', () => {
      expect(shouldHideMessage(msg({ login: 'ana' }), filters)).toBe(false);
    });

    it('does not hide a message with no login', () => {
      expect(shouldHideMessage(msg({ login: '' }), filters)).toBe(false);
    });

    it('does not match on a partial login', () => {
      expect(shouldHideMessage(msg({ login: 'spambot2' }), filters)).toBe(false);
      expect(shouldHideMessage(msg({ login: 'spam' }), filters)).toBe(false);
    });

    it('hides a listed login typing from another channel during shared chat', () => {
      // A hidden user is hidden wherever they type from - the only reading
      // that is not surprising.
      expect(shouldHideMessage(msg({ login: 'spambot', sourceRoomId: '999' }), filters)).toBe(true);
    });
  });

  it('hides when either rule matches', () => {
    const filters = { hideCommands: true, hiddenLogins: ['spambot'] };

    expect(shouldHideMessage(msg({ login: 'ana', text: '!ping' }), filters)).toBe(true);
    expect(shouldHideMessage(msg({ login: 'spambot', text: 'hello' }), filters)).toBe(true);
    expect(shouldHideMessage(msg({ login: 'ana', text: 'hello' }), filters)).toBe(false);
  });
});
