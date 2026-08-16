import { parseIrcLine, toChatMessage, toModerationAction } from '@/utils/ircParser';
import { describe, expect, it } from 'vitest';
import { clearchatLine, clearmsgLine, privmsgLine, type ChatHoseOptions } from './chatHose';

/*
 * The hose is only useful if what it emits is indistinguishable from real
 * Twitch traffic to the actual parser. If a generated line is subtly malformed,
 * a load test measures the wrong thing and the first hour of debugging goes
 * into the instrument rather than the overlay.
 *
 * So these run generated lines through the REAL parser, not a copy of it.
 */

function options(over: Partial<ChatHoseOptions> = {}): ChatHoseOptions {
  return {
    rate: 10,
    emoteChance: 0,
    thirdPartyChance: 0,
    moderationChance: 0,
    chatters: 10,
    channel: 'loadtest',
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
