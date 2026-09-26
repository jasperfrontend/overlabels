import { splitByEmotePositions } from '@/composables/useEmoteParser';
import { encodeHtml } from '@/utils/tagParser';
import type { ChatMessage } from './ircParser';

/**
 * Project the recent emotes of a chat window into the flat dotted data slots
 * the renderer iterates as `[[[foreach:emotes as e]]]`.
 *
 * One slot per emote OCCURRENCE, not per message: a viewer typing an emote five
 * times produces five items, in the order they were typed, with `n` saying which
 * of the message's emotes each one is. That is the shape Chat Emote Bubbles
 * needs (one bubble per emote, staggered per message), and it is the natural
 * one for anything that reacts to emotes rather than to messages.
 *
 * Same arrangement as chatSlots.ts: `resolveIterable` synthesises an array from
 * these keys, so the loop needs no DSL change, and the composable owns the
 * buffer while this owns the shape. Pure on purpose.
 */

/** Prefix every key shares. The caller strips these before merging a new set. */
export const EMOTE_SLOT_PREFIX = 'emotes.';

/**
 * Where a third-party emote token resolves to.
 *
 * `null` means "not an emote" - or not one we know about YET, since the emote
 * library loads asynchronously. Twitch's own emotes never go through this: they
 * arrive with positions and ids on the wire and resolve without any library.
 */
export type EmoteUrlResolver = (token: string) => string | null;

export interface EmoteOccurrence {
  /** Stable per occurrence: the message id plus the position within it. */
  id: string;
  /** The emote's code, exactly as typed (`PogChamp`, `KEKW`). */
  name: string;
  /** Image URL, on the emote provider's CDN. */
  url: string;
  /** Which of its message's emotes this is, 0 first. */
  n: number;
  /** Two stable pseudo-random coordinates in [0, 100), and a third for variety. */
  x: number;
  y: number;
  seed: number;
  /** Copied from the message, so a template can credit or filter by chatter. */
  messageId: string;
  userId: string;
  login: string;
  author: string;
  color: string;
  at: number;
  sourceRoomId: string;
}

/**
 * Twitch's CDN size for a bubble.
 *
 * `2.0` is 56 px, which is crisp inside a 96 px bubble and half the bytes of
 * `3.0`. The chat feed uses `1.0` because it draws emotes at text height; a
 * bubble draws one emote large, so it pays for the bigger image once.
 */
export const TWITCH_EMOTE_CDN_SIZE = '2.0';

export function twitchEmoteUrl(id: string, size: string = TWITCH_EMOTE_CDN_SIZE): string {
  return `https://static-cdn.jtvnw.net/emoticons/v2/${id}/default/dark/${size}`;
}

/**
 * FNV-1a over a string, folded to [0, 100).
 *
 * The spawn point of a bubble has to be random-looking AND stable: the renderer
 * rebuilds every slot on every change, and a coordinate that moved between
 * rebuilds would make a bubble jump mid-flight. Hashing the occurrence id gives
 * a number that is different per bubble and identical per rebuild, with no
 * state to keep.
 */
export function stableHash(input: string, salt: number = 0): number {
  let h = 0x811c9dc5 ^ salt;
  for (let i = 0; i < input.length; i++) {
    h ^= input.charCodeAt(i);
    h = Math.imul(h, 0x01000193) >>> 0;
  }

  return h % 100;
}

/**
 * Every emote in a message, in order.
 *
 * Twitch emotes come from the IRC positions and need no library. Anything left
 * over is split on whitespace and offered to the resolver token by token, the
 * same way the chat feed's HTML is built, so the set of things that count as an
 * emote here is exactly the set that renders as one in `[[[msg.html]]]`.
 */
export function emoteOccurrences(message: ChatMessage, resolve?: EmoteUrlResolver): EmoteOccurrence[] {
  const found: Array<{ name: string; url: string }> = [];

  for (const segment of splitByEmotePositions(message.text, message.emotes)) {
    if (segment.kind === 'emote') {
      found.push({ name: segment.text, url: twitchEmoteUrl(segment.id) });
      continue;
    }
    if (!resolve) continue;

    for (const token of segment.text.split(/\s+/)) {
      if (!token) continue;
      const url = resolve(token);
      if (url) found.push({ name: token, url });
    }
  }

  return found.map(({ name, url }, n) => {
    const id = `${message.id}:${n}`;

    return {
      id,
      name,
      url,
      n,
      x: stableHash(id, 1),
      y: stableHash(id, 2),
      seed: stableHash(id, 3),
      messageId: message.id,
      userId: message.userId,
      login: message.login,
      author: message.author,
      color: message.color,
      at: message.at,
      sourceRoomId: message.sourceRoomId,
    };
  });
}

/**
 * Safe HTML for `emotes.N.html`.
 *
 * Nothing from chat reaches the markup unescaped: the name is the emote code
 * (already restricted to the characters an emote code can have, but escaped
 * anyway) and the URL was built by this app from an emote id, never from the
 * message. That is the contract that lets the renderer list this field as
 * html-safe.
 */
export function emoteHtml(e: EmoteOccurrence): string {
  return `<img class="overlay-emote" alt="${encodeHtml(e.name)}" src="${encodeHtml(e.url)}">`;
}

/**
 * Build the complete `emotes.*` slot set for the current buffer.
 *
 * Index 0 is the OLDEST occurrence, so `:last-child` is the newest - the same
 * rule as the chat loop.
 */
export function emoteSlots(list: readonly EmoteOccurrence[]): Record<string, string> {
  const slots: Record<string, string> = {
    'emotes.count': String(list.length),
  };

  list.forEach((e, i) => {
    const k = `${EMOTE_SLOT_PREFIX}${i}.`;
    slots[`${k}id`] = e.id;
    slots[`${k}name`] = e.name;
    slots[`${k}url`] = e.url;
    slots[`${k}html`] = emoteHtml(e);
    slots[`${k}n`] = String(e.n);
    slots[`${k}x`] = String(e.x);
    slots[`${k}y`] = String(e.y);
    slots[`${k}seed`] = String(e.seed);
    slots[`${k}message_id`] = e.messageId;
    slots[`${k}author`] = e.author;
    slots[`${k}login`] = e.login;
    slots[`${k}color`] = e.color;
    slots[`${k}at`] = String(e.at);
    slots[`${k}source_channel`] = e.sourceRoomId;
  });

  return slots;
}

/**
 * Merge a fresh slot set into a data object, dropping every previous `emotes.*`
 * key first - the withChatSlots rule, for the same reason: a shrinking buffer
 * must not leave a deleted emote behind to resurface later.
 */
export function withEmoteSlots(data: Record<string, unknown>, list: readonly EmoteOccurrence[]): Record<string, unknown> {
  const next: Record<string, unknown> = {};

  for (const key in data) {
    if (key === 'emotes.count' || key.startsWith(EMOTE_SLOT_PREFIX)) continue;
    next[key] = data[key];
  }

  return Object.assign(next, emoteSlots(list));
}
