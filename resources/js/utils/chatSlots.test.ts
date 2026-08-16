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
