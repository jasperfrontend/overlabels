import { describe, expect, it } from 'vitest';
import { chatSlots, withChatSlots } from './chatSlots';
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

describe('chatSlots', () => {
  it('emits the flat dotted keys resolveIterable synthesizes an array from', () => {
    const slots = chatSlots([msg({ id: 'a' }), msg({ id: 'b', author: 'Bo', login: 'bo' })]);

    expect(slots['chat.count']).toBe('2');
    expect(slots['chat.0.id']).toBe('a');
    expect(slots['chat.1.author']).toBe('Bo');
    expect(slots['chat.1.login']).toBe('bo');
  });

  it('renders booleans as 1 and empty, never the word false', () => {
    // A bare [[[msg.mod]]] must print nothing for a non-mod. The string
    // 'false' would be truthy when printed and is only handled as falsy by
    // the conditional branch, so it would leak the word into the overlay.
    const slots = chatSlots([msg({ isMod: true, isVip: false })]);

    expect(slots['chat.0.mod']).toBe('1');
    expect(slots['chat.0.vip']).toBe('');
  });

  it('keeps timestamps in Unix seconds', () => {
    expect(chatSlots([msg()])['chat.0.at']).toBe('1755273600');
  });

  it('orders index 0 as the oldest message', () => {
    const slots = chatSlots([msg({ id: 'older' }), msg({ id: 'newer' })]);

    expect(slots['chat.0.id']).toBe('older');
    expect(slots['chat.1.id']).toBe('newer');
  });

  it('leaves source_channel empty for a native message', () => {
    expect(chatSlots([msg()])['chat.0.source_channel']).toBe('');
    expect(chatSlots([msg({ sourceRoomId: '999' })])['chat.0.source_channel']).toBe('999');
  });

  it('reports an empty window without emitting any rows', () => {
    const slots = chatSlots([]);

    expect(slots['chat.count']).toBe('0');
    expect(Object.keys(slots)).toEqual(['chat.count']);
  });
});

describe('withChatSlots', () => {
  it('leaves unrelated data untouched', () => {
    const out = withChatSlots({ 'c:kofi:total_received': '42', 'twitch.user.display_name': 'Ana' }, [msg()]);

    expect(out['c:kofi:total_received']).toBe('42');
    expect(out['twitch.user.display_name']).toBe('Ana');
    expect(out['chat.0.author']).toBe('Ana');
  });

  it('drops stale rows when the window shrinks', () => {
    // The resurrection bug this prevents: after a moderator clears chat,
    // a leftover chat.7.* would be invisible while count is small and would
    // reappear the moment the window grew back past it.
    const withThree = withChatSlots({}, [msg({ id: 'a' }), msg({ id: 'b' }), msg({ id: 'c' })]);
    const afterClear = withChatSlots(withThree, []);

    expect(afterClear['chat.count']).toBe('0');
    expect(afterClear['chat.2.id']).toBeUndefined();
    expect(Object.keys(afterClear).some((k) => k.startsWith('chat.') && k !== 'chat.count')).toBe(false);
  });

  it('does not mutate the object it was given', () => {
    const original: Record<string, unknown> = { 'chat.count': '5', 'chat.0.id': 'stale', keep: 'yes' };
    const out = withChatSlots(original, [msg({ id: 'fresh' })]);

    expect(original['chat.0.id']).toBe('stale');
    expect(out['chat.0.id']).toBe('fresh');
    expect(out['keep']).toBe('yes');
  });
});

describe('chatSlots / the html slot', () => {
  it('is absent unless a renderer is supplied', () => {
    // That slot is rendered WITHOUT escaping, so a caller with no emote
    // pipeline must never produce one that merely claims to be safe HTML.
    expect(chatSlots([msg()])['chat.0.html']).toBeUndefined();
  });

  it('is produced by the supplied renderer', () => {
    const slots = chatSlots([msg({ text: 'Kappa' })], (m) => `<img alt="${m.text}">`);

    expect(slots['chat.0.html']).toBe('<img alt="Kappa">');
    // The plain text slot stays alongside it, so a template can choose.
    expect(slots['chat.0.text']).toBe('Kappa');
  });

  it('is cleared along with the rest of the window', () => {
    const withOne = withChatSlots({}, [msg()], () => '<img>');
    const afterClear = withChatSlots(withOne, []);

    expect(afterClear['chat.0.html']).toBeUndefined();
  });
});

describe('chatSlots / the badge_images slot', () => {
  it('is absent unless a renderer is supplied', () => {
    // Also rendered WITHOUT escaping. A template asking for badge art before
    // the manifest lands should get nothing, not broken <img> elements.
    expect(chatSlots([msg()])['chat.0.badge_images']).toBeUndefined();
    expect(chatSlots([msg()], () => '<img>')['chat.0.badge_images']).toBeUndefined();
  });

  it('is produced by the supplied renderer', () => {
    const slots = chatSlots([msg()], undefined, () => '<img class="ol-badge">');

    expect(slots['chat.0.badge_images']).toBe('<img class="ol-badge">');
  });

  it('leaves the bare badge names alone', () => {
    // `msg.badges` is what templates use for CSS classes and must not change
    // when artwork is added alongside it.
    const slots = chatSlots([msg({ badges: 'moderator subscriber' })], undefined, () => '<img>');

    expect(slots['chat.0.badges']).toBe('moderator subscriber');
  });

  it('is cleared along with the rest of the window', () => {
    const withOne = withChatSlots({}, [msg()], undefined, () => '<img>');
    const afterClear = withChatSlots(withOne, []);

    expect(afterClear['chat.0.badge_images']).toBeUndefined();
  });
});
