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
 * Turns a message into SAFE HTML for `chat.N.html`.
 *
 * The contract is the one `useEmoteParser` already keeps for alerts: every
 * piece of chatter-supplied text is entity-escaped BEFORE any markup is added,
 * so the only tags in the result are `<img>` elements this app generated. That
 * slot is rendered without escaping, so a producer that breaks this contract is
 * writing an XSS hole - which is why the type exists rather than the renderer
 * just accepting any string.
 */
export type EmoteRenderer = (message: ChatMessage) => string;

/**
 * Turns a message's badges into SAFE HTML for `chat.N.badge_images`.
 *
 * Same contract as EmoteRenderer, and the same reason for being a named type
 * rather than a bare string: this slot is rendered without entity-encoding, so
 * a producer that echoes anything from chat into it is writing an XSS hole.
 * `badgeRenderer.ts` is the only implementation, and it emits `<img>` elements
 * built entirely from the server-supplied badge manifest.
 */
export type BadgeRenderer = (message: ChatMessage) => string;

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
export function chatSlots(messages: readonly ChatMessage[], toHtml?: EmoteRenderer, toBadges?: BadgeRenderer): Record<string, string> {
  const slots: Record<string, string> = {
    'chat.count': String(messages.length),
  };

  messages.forEach((m, i) => {
    const k = `${CHAT_SLOT_PREFIX}${i}.`;
    // Only emitted when a renderer is supplied, so a caller with no emote
    // pipeline never produces a slot that claims to be safe HTML.
    if (toHtml) slots[`${k}html`] = toHtml(m);
    // Same rule for badge art: no manifest, no slot. A template asking for
    // badge images before the manifest loads gets nothing rather than broken
    // <img> elements pointing at undefined.
    if (toBadges) slots[`${k}badge_images`] = toBadges(m);
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
export function withChatSlots(
  data: Record<string, unknown>,
  messages: readonly ChatMessage[],
  toHtml?: EmoteRenderer,
  toBadges?: BadgeRenderer,
): Record<string, unknown> {
  const next: Record<string, unknown> = {};

  for (const key in data) {
    if (key === 'chat.count' || key.startsWith(CHAT_SLOT_PREFIX)) continue;
    next[key] = data[key];
  }

  return Object.assign(next, chatSlots(messages, toHtml, toBadges));
}
