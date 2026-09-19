import { type ChatSampleOptions, clearchatLine, clearmsgLine, createChatSampleFeed, privmsgLine } from '@/utils/chatSample';
import { parseIrcLine, toChatMessage, toModerationAction } from '@/utils/ircParser';
import { afterEach, describe, expect, it, vi } from 'vitest';

/*
 * A sample line is only useful if it is indistinguishable from real Twitch
 * traffic to the actual parser. If a generated line is subtly malformed, a load
 * test measures the wrong thing and the designer previews a look nobody will
 * ever get - and the first hour of debugging goes into the fixture rather than
 * the overlay.
 *
 * So these run generated lines through the REAL parser, not a copy of it.
 */

function options(over: Partial<ChatSampleOptions> = {}): ChatSampleOptions {
  return {
    emoteChance: 0,
    thirdPartyChance: 0,
    firstChance: 0,
    chatters: 10,
    channel: 'sample',
    roomId: '1',
    foreignChance: 0,
    ...over,
  };
}

describe('privmsgLine', () => {
  it('parses as a chat message', () => {
    const message = toChatMessage(parseIrcLine(privmsgLine(options(), 0))!);

    expect(message).not.toBeNull();
    expect(message!.login).toBe('chatter0');
    expect(message!.author).toBe('Chatter0');
    expect(message!.text.length).toBeGreaterThan(0);
    expect(message!.id).not.toBe('');
  });

  it('cycles logins through the chatter pool', () => {
    // Drives unique-chatter behaviour: a pool of 3 must produce 3 logins.
    const logins = new Set(Array.from({ length: 12 }, (_, i) => toChatMessage(parseIrcLine(privmsgLine(options({ chatters: 3 }), i))!)!.login));

    expect(logins.size).toBe(3);
  });

  it('emits emote positions that actually line up with the text', () => {
    // THE thing worth testing. The renderer slices the message by these
    // indices, so an off-by-one would render half an emote name and look like
    // a renderer bug rather than a fixture bug.
    const line = privmsgLine(options({ emoteChance: 1 }), 0);
    const message = toChatMessage(parseIrcLine(line)!)!;

    expect(message.emotes.length).toBeGreaterThan(0);

    for (const emote of message.emotes) {
      const sliced = message.text.slice(emote.begin, emote.end + 1);
      expect(sliced).toMatch(/^[A-Za-z0-9]+$/);
      expect(sliced.length).toBeGreaterThan(1);
    }
  });

  it('emits no emote tag when emotes are switched off', () => {
    expect(toChatMessage(parseIrcLine(privmsgLine(options({ emoteChance: 0 }), 0))!)!.emotes).toEqual([]);
  });

  it('produces badges the parser reads back', () => {
    // Sampled across seeds because the badge set is random per message.
    const messages = Array.from({ length: 40 }, (_, i) => toChatMessage(parseIrcLine(privmsgLine(options(), i))!)!);

    expect(messages.some((m) => m.isMod)).toBe(true);
    expect(messages.some((m) => m.isSubscriber)).toBe(true);
    expect(messages.every((m) => m.badgeVersions.every((b) => b.includes('/')))).toBe(true);
  });

  it('flags a first-ever message when asked, and never otherwise', () => {
    // The accent-coloured "first message" chip is one of the things a streamer
    // picks a colour for in the designer, so the preview turns this up. At the
    // 2% a real chat runs at, it would not appear once in a short session.
    expect(toChatMessage(parseIrcLine(privmsgLine(options({ firstChance: 1 }), 0))!)!.isFirstMessage).toBe(true);

    const none = Array.from({ length: 40 }, (_, i) => toChatMessage(parseIrcLine(privmsgLine(options({ firstChance: 0 }), i))!)!);
    expect(none.some((m) => m.isFirstMessage)).toBe(false);
  });

  it('can emit Shared Chat messages', () => {
    const message = toChatMessage(parseIrcLine(privmsgLine(options({ foreignChance: 1 }), 0))!)!;

    expect(message.sourceRoomId).toBe('999999');
  });

  it('marks a native message as native', () => {
    expect(toChatMessage(parseIrcLine(privmsgLine(options({ foreignChance: 0 }), 0))!)!.sourceRoomId).toBe('');
  });

  it('gives every message a distinct id', () => {
    const ids = new Set(Array.from({ length: 50 }, (_, i) => toChatMessage(parseIrcLine(privmsgLine(options(), i))!)!.id));

    expect(ids.size).toBe(50);
  });
});

describe('moderation lines', () => {
  it('emits a CLEARMSG the parser turns into a single-message delete', () => {
    const action = toModerationAction(parseIrcLine(clearmsgLine(options(), 'abc-123'))!);

    expect(action).toEqual({ type: 'delete_message', messageId: 'abc-123' });
  });

  it('emits a targeted CLEARCHAT that purges one user, not the room', () => {
    // The failure mode this guards: a purge being mistaken for a room clear
    // would wipe the overlay on every timeout.
    const action = toModerationAction(parseIrcLine(clearchatLine(options(), 'chatter7'))!);

    expect(action?.type).not.toBe('clear_all');
  });

  it('emits an untargeted CLEARCHAT that clears the room', () => {
    expect(toModerationAction(parseIrcLine(clearchatLine(options()))!)).toEqual({ type: 'clear_all' });
  });
});

describe('createChatSampleFeed', () => {
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  function sink() {
    const lines: string[] = [];
    return { lines, injectRawLine: (raw: string) => lines.push(raw) };
  }

  it('bursts messages the parser reads back as chat', () => {
    const target = sink();
    createChatSampleFeed(target).burst(5);

    expect(target.lines).toHaveLength(5);
    for (const line of target.lines) {
      expect(toChatMessage(parseIrcLine(line)!)).not.toBeNull();
    }
  });

  it('seeds from chance rather than from the module counter', () => {
    // The counter walks the pool in order, which is right for spreading a load
    // test over distinct users and wrong for a preview, where it reads as a
    // numbered list rather than as people talking. Pinning Math.random pins
    // the login, which a counter-driven seed could never do.
    const target = sink();
    vi.spyOn(Math, 'random').mockReturnValue(0.5);

    createChatSampleFeed(target, { chatters: 4 }).burst(3);

    expect(target.lines.map((line) => toChatMessage(parseIrcLine(line)!)!.login)).toEqual(['chatter2', 'chatter2', 'chatter2']);
  });

  it('runs at the rate it is given, in messages per minute', () => {
    vi.useFakeTimers();
    const target = sink();
    const feed = createChatSampleFeed(target);

    feed.setRate(60);
    vi.advanceTimersByTime(5_000);
    expect(target.lines).toHaveLength(5);

    // Rate changes reschedule the one timer rather than adding a second.
    feed.setRate(120);
    vi.advanceTimersByTime(5_000);
    expect(target.lines).toHaveLength(15);

    feed.stop();
  });

  it('stops on a rate of zero and leaves the window alone', () => {
    vi.useFakeTimers();
    const target = sink();
    const feed = createChatSampleFeed(target);

    feed.setRate(60);
    vi.advanceTimersByTime(2_000);
    feed.setRate(0);
    vi.advanceTimersByTime(60_000);

    expect(target.lines).toHaveLength(2);
    feed.stop();
  });

  it('clears the window the way a moderator would', () => {
    const target = sink();
    createChatSampleFeed(target).clear();

    expect(toModerationAction(parseIrcLine(target.lines[0])!)).toEqual({ type: 'clear_all' });
  });
});
