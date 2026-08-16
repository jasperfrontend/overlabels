import type { ChatMessage } from './ircParser';

/**
 * Project a chat window into the flat dotted data slots the renderer iterates.
 *
 * `resolveIterable` synthesizes an array from keys like `chat.0.author` plus
 * `chat.count`, with no registration anywhere, which is why a chat feed needs no
 * DSL change at all: put these keys in the data map and
 * `[[[foreach:chat as msg]]]` already works.
 *
 * Pure on purpose - the composable owns the socket, this owns the shape.
 */

/** Prefix every key shares. The caller strips these before merging a new set. */
export const CHAT_SLOT_PREFIX = 'chat.';

/**
 * Booleans render as `1` / empty string rather than `true` / `false`.
 *
 * `useConditionalTemplates` treats `'0'` and `''` as falsy but the STRING
 * `'false'` is a special case that only its boolean branch knows about, and a
 * bare `[[[msg.mod]]]` would print the word "false" into the overlay. Empty is
 * both falsy in a condition and invisible when printed, which is what the
 * null-over-placeholder rule wants.
 */
function flag(value: boolean): string {
  return value ? '1' : '';
}

/**
 * Build the complete `chat.*` slot set for the current window.
 *
 * Index 0 is the OLDEST visible message, so a template iterating in order
 * renders top-to-bottom the way Twitch chat reads.
 */
export function chatSlots(messages: readonly ChatMessage[]): Record<string, string> {
  const slots: Record<string, string> = {
    'chat.count': String(messages.length),
  };

  messages.forEach((m, i) => {
    const k = `${CHAT_SLOT_PREFIX}${i}.`;
    slots[`${k}id`] = m.id;
    slots[`${k}author`] = m.author;
    slots[`${k}login`] = m.login;
    slots[`${k}text`] = m.text;
    slots[`${k}color`] = m.color;
    slots[`${k}badges`] = m.badges;
    slots[`${k}at`] = String(m.at);
    slots[`${k}mod`] = flag(m.isMod);
    slots[`${k}sub`] = flag(m.isSubscriber);
    slots[`${k}vip`] = flag(m.isVip);
    slots[`${k}broadcaster`] = flag(m.isBroadcaster);
    slots[`${k}first`] = flag(m.isFirstMessage);
    // Empty for a native message. Non-empty means the message was
    // duplicated in from another channel during a Shared Chat session, so a
    // template can mark it rather than passing a partner's audience off as
    // its own.
    slots[`${k}source_channel`] = m.sourceRoomId;
  });

  return slots;
}

/**
 * Merge a fresh slot set into a data object, dropping every previous `chat.*`
 * key first.
 *
 * Replacing rather than patching is what keeps a shrinking window honest. After
 * a moderator clears chat, leaving `chat.7.author` behind would be invisible
 * while `chat.count` is small, and would reappear the moment the window grew
 * back past it - a deleted message resurrected by an unrelated new one.
 */
export function withChatSlots(data: Record<string, unknown>, messages: readonly ChatMessage[]): Record<string, unknown> {
  const next: Record<string, unknown> = {};

  for (const key in data) {
    if (key === 'chat.count' || key.startsWith(CHAT_SLOT_PREFIX)) continue;
    next[key] = data[key];
  }

  return Object.assign(next, chatSlots(messages));
}
