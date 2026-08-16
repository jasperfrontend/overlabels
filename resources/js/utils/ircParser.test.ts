import { describe, expect, it } from 'vitest';
import { parseEmoteTag, parseIrcLine, parseTags, toChatMessage, toModerationAction } from './ircParser';

/**
 * The wire format is a stranger's keyboard.
 *
 * Every value here arrives from an anonymous Twitch IRC connection and is
 * attacker-influenced: display names, message text, colours, even the tag
 * structure. These tests pin the parsing rules that keep that survivable, and in
 * particular the moderation ones - a deleted message that stays on the overlay
 * is burned into the stream, the VOD and every clip of it.
 */

/** A realistic tags-enabled PRIVMSG, of the shape Twitch actually sends. */
const PRIVMSG =
  '@badge-info=subscriber/13;badges=broadcaster/1,subscriber/12;color=#1E90FF;display-name=CasualElephant;' +
  'first-msg=0;id=abc-123;mod=0;room-id=555;subscriber=1;tmi-sent-ts=1755273600000;user-id=777 ' +
  ':casualelephant!casualelephant@casualelephant.tmi.twitch.tv PRIVMSG #casualelephant :hello chat Kappa';

describe('parseTags', () => {
  it('parses key/value pairs', () => {
    expect(parseTags('a=1;b=2')).toEqual({ a: '1', b: '2' });
  });

  it('treats a valueless tag as present-but-empty', () => {
    expect(parseTags('color=;mod=1')).toEqual({ color: '', mod: '1' });
    expect(parseTags('flag;mod=1')).toEqual({ flag: '', mod: '1' });
  });

  it('unescapes the IRCv3 escape sequences', () => {
    // A display name with a space and a semicolon would otherwise split the
    // tag list into bogus extra tags.
    expect(parseTags(String.raw`system-msg=hi\sthere\:friend;x=a\\b`)).toEqual({
      'system-msg': 'hi there;friend',
      x: 'a\\b',
    });
  });

  it('drops a trailing lone backslash rather than throwing', () => {
    expect(parseTags('a=oops\\')).toEqual({ a: 'oops' });
  });
});

describe('parseIrcLine', () => {
  it('splits tags, prefix, command, params and trailing', () => {
    const line = parseIrcLine(PRIVMSG)!;

    expect(line.command).toBe('PRIVMSG');
    expect(line.params).toEqual(['#casualelephant']);
    expect(line.trailing).toBe('hello chat Kappa');
    expect(line.tags['display-name']).toBe('CasualElephant');
  });

  it('keeps spaces and colons inside the trailing parameter', () => {
    const line = parseIrcLine(':a!a@a PRIVMSG #c :look: a b  c')!;

    expect(line.trailing).toBe('look: a b  c');
  });

  it('handles a line with no tags and no prefix', () => {
    const line = parseIrcLine('PING :tmi.twitch.tv')!;

    expect(line.command).toBe('PING');
    expect(line.trailing).toBe('tmi.twitch.tv');
  });

  it('returns null for blank input', () => {
    expect(parseIrcLine('')).toBeNull();
    expect(parseIrcLine('   ')).toBeNull();
  });

  it('parses an unknown command rather than throwing', () => {
    // A future Twitch addition should be an ignorable line, not an
    // exception inside a socket handler.
    expect(parseIrcLine(':tmi.twitch.tv SOMETHINGNEW #c :x')?.command).toBe('SOMETHINGNEW');
  });
});

describe('toChatMessage', () => {
  it('normalises a PRIVMSG', () => {
    const msg = toChatMessage(parseIrcLine(PRIVMSG)!)!;

    expect(msg.id).toBe('abc-123');
    expect(msg.userId).toBe('777');
    expect(msg.login).toBe('casualelephant');
    expect(msg.author).toBe('CasualElephant');
    expect(msg.text).toBe('hello chat Kappa');
    expect(msg.color).toBe('#1E90FF');
    expect(msg.badges).toBe('broadcaster subscriber');
  });

  it('keeps badge versions alongside the bare names', () => {
    // `badges` is a published contract used for CSS class names and must stay
    // version-free. Badge ARTWORK needs the version, because a 3-month and a
    // 12-month subscriber badge are different images from the same set.
    const msg = toChatMessage(parseIrcLine(PRIVMSG)!)!;

    expect(msg.badges).toBe('broadcaster subscriber');
    expect(msg.badgeVersions).toEqual(['broadcaster/1', 'subscriber/12']);
  });

  it('has no badge versions when the tag is absent', () => {
    const msg = toChatMessage(parseIrcLine('@id=1 :a!a@a PRIVMSG #c :hi')!)!;

    expect(msg.badgeVersions).toEqual([]);
  });

  it('ignores a badge entry with no version rather than inventing one', () => {
    const msg = toChatMessage(parseIrcLine('@id=1;badges=moderator/1,broken :a!a@a PRIVMSG #c :hi')!)!;

    expect(msg.badgeVersions).toEqual(['moderator/1']);
    // The name still counts for styling and for isMod.
    expect(msg.badges).toBe('moderator broken');
  });

  it('converts tmi-sent-ts from milliseconds to seconds', () => {
    // Every timestamp in the project is Unix SECONDS; the wire is ms.
    expect(toChatMessage(parseIrcLine(PRIVMSG)!)!.at).toBe(1755273600);
  });

  it('falls back to the login when no display name is set', () => {
    const line = parseIrcLine('@display-name=;id=1 :bob!bob@bob PRIVMSG #c :hi')!;

    expect(toChatMessage(line)!.author).toBe('bob');
  });

  it('treats the broadcaster as a moderator even though mod=0', () => {
    const msg = toChatMessage(parseIrcLine(PRIVMSG)!)!;

    expect(msg.isBroadcaster).toBe(true);
    expect(msg.isMod).toBe(true);
  });

  it('counts a founder as a subscriber', () => {
    const line = parseIrcLine('@badges=founder/0;id=1 :a!a@a PRIVMSG #c :hi')!;

    expect(toChatMessage(line)!.isSubscriber).toBe(true);
  });

  it('does not treat a non-PRIVMSG as a chat message', () => {
    expect(toChatMessage(parseIrcLine('PING :tmi.twitch.tv')!)).toBeNull();
  });

  it('preserves message text verbatim, leaving escaping to the renderer', () => {
    const line = parseIrcLine(':a!a@a PRIVMSG #c :<img src=x> and [[[c:kofi:total_received]]]')!;

    // The parser must not sanitise: the renderer escapes and defuses tag
    // brackets at substitution time, and doing it twice would corrupt text.
    expect(toChatMessage(line)!.text).toBe('<img src=x> and [[[c:kofi:total_received]]]');
  });
});

describe('toChatMessage / Shared Chat', () => {
  it('marks a message duplicated in from another channel', () => {
    const line = parseIrcLine('@id=1;room-id=555;source-room-id=999;source-badges=moderator/1 :a!a@a PRIVMSG #c :hi')!;
    const msg = toChatMessage(line)!;

    expect(msg.sourceRoomId).toBe('999');
    // Their standing in the channel they typed in, which is what Twitch's
    // own UI shows - a partner's moderator reads as a moderator.
    expect(msg.badges).toBe('moderator');
    expect(msg.isMod).toBe(true);
    // Their versions too, so badge art can resolve them - against the GLOBAL
    // map only, since their channel-specific art is not ours to guess at.
    expect(msg.badgeVersions).toEqual(['moderator/1']);
  });

  it('treats a native message as native even when source-room-id is echoed back', () => {
    // Twitch sets source-room-id equal to room-id on the originating
    // channel's own copy. That is not a foreign message.
    const line = parseIrcLine('@id=1;room-id=555;source-room-id=555;badges=vip/1 :a!a@a PRIVMSG #c :hi')!;
    const msg = toChatMessage(line)!;

    expect(msg.sourceRoomId).toBe('');
    expect(msg.badges).toBe('vip');
  });
});

describe('toModerationAction', () => {
  it('deletes a single message on CLEARMSG', () => {
    const line = parseIrcLine('@login=bob;target-msg-id=abc-123 :tmi.twitch.tv CLEARMSG #c :some text')!;

    expect(toModerationAction(line)).toEqual({ type: 'delete_message', messageId: 'abc-123' });
  });

  it('purges by user id on a timeout or ban', () => {
    const line = parseIrcLine('@ban-duration=600;target-user-id=777 :tmi.twitch.tv CLEARCHAT #c :bob')!;

    expect(toModerationAction(line)).toEqual({ type: 'purge_user', userId: '777' });
  });

  it('falls back to the login when the target id tag is missing', () => {
    // Failing open here would wipe the whole overlay on every timeout;
    // failing closed would leave a banned chatter's messages on stream.
    const line = parseIrcLine(':tmi.twitch.tv CLEARCHAT #c :bob')!;

    expect(toModerationAction(line)).toEqual({ type: 'purge_login', login: 'bob' });
  });

  it('clears everything only when no target is named', () => {
    const line = parseIrcLine(':tmi.twitch.tv CLEARCHAT #c')!;

    expect(toModerationAction(line)).toEqual({ type: 'clear_all' });
  });

  it('ignores a CLEARMSG with no target id', () => {
    expect(toModerationAction(parseIrcLine(':tmi.twitch.tv CLEARMSG #c :x')!)).toBeNull();
  });

  it('is not triggered by an ordinary message', () => {
    expect(toModerationAction(parseIrcLine(PRIVMSG)!)).toBeNull();
  });
});

describe('parseEmoteTag', () => {
  it('parses a single emote occurrence', () => {
    expect(parseEmoteTag('25:0-4')).toEqual([{ id: '25', begin: 0, end: 4 }]);
  });

  it('parses repeats of the same emote', () => {
    expect(parseEmoteTag('25:0-4,6-10')).toEqual([
      { id: '25', begin: 0, end: 4 },
      { id: '25', begin: 6, end: 10 },
    ]);
  });

  it('parses several different emotes', () => {
    expect(parseEmoteTag('25:0-4/1902:6-10')).toEqual([
      { id: '25', begin: 0, end: 4 },
      { id: '1902', begin: 6, end: 10 },
    ]);
  });

  it('returns nothing for a message with no emotes', () => {
    expect(parseEmoteTag('')).toEqual([]);
  });

  it('skips malformed segments instead of throwing', () => {
    // Runs inside a socket handler on attacker-influenced input, so a
    // surprise here must never be able to take the overlay down.
    expect(parseEmoteTag('garbage')).toEqual([]);
    expect(parseEmoteTag('25:notarange/1902:6-10')).toEqual([{ id: '1902', begin: 6, end: 10 }]);
    expect(parseEmoteTag(':0-4')).toEqual([]);
    expect(parseEmoteTag('25:5-1')).toEqual([]);
    expect(parseEmoteTag('25:-3--1')).toEqual([]);
  });

  it('rides along on a parsed message', () => {
    const line = parseIrcLine('@emotes=25:0-4;id=1 :a!a@a PRIVMSG #c :Kappa hello')!;

    expect(toChatMessage(line)!.emotes).toEqual([{ id: '25', begin: 0, end: 4 }]);
  });

  it('leaves emotes empty when the tag is absent', () => {
    expect(toChatMessage(parseIrcLine(':a!a@a PRIVMSG #c :plain')!)!.emotes).toEqual([]);
  });
});
