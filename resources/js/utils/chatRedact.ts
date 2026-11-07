import type { ChatMessage } from './ircParser';

/**
 * Redacted messages: a chatter types `!hide some text` and the chat overlay
 * draws "some text" in the Redacted font, as bars. Only on this overlay - the
 * message is in Twitch chat as normal, for everyone, and in the VOD.
 *
 * Off unless the streamer turned it on at /settings/integrations/redacted-messages.
 * The word arrives as `chat_redact_command` in the render payload, and only
 * when the feature is on, so an empty word here means off.
 *
 * The overlay does this itself, from its own chat socket. No bot and no
 * server: the bot would see the same line later and could only relay it.
 */

/** Same shape as the Plinko word: one word, letters, digits or `_`. */
const COMMAND_PATTERN = /^[a-z0-9_]{1,25}$/;

/**
 * Normalise the payload's command word. Anything unusable means off, so an
 * older server or a malformed payload degrades to a normal chat feed rather
 * than throwing inside the socket handler.
 */
export function toRedactCommand(raw: unknown): string {
  if (typeof raw !== 'string') return '';
  const word = raw.trim().toLowerCase();

  return COMMAND_PATTERN.test(word) ? word : '';
}

/**
 * Turn `!hide some text` into a redacted message with the text "some text".
 *
 * Every other message comes back as the same object. `!hidex` is a different
 * command and `!hide` on its own has nothing to hide, so both are left alone
 * (and the `hide_commands` filter treats them like any other command).
 *
 * Emote positions are dropped rather than shifted: a redacted message renders
 * no emote images at all, and must not feed the emote bubbles either, or the
 * emotes would give the message away.
 */
export function redactMessage(message: ChatMessage, command: string): ChatMessage {
  if (!command) return message;

  const text = message.text.trimStart();
  const prefix = `!${command}`;
  if (text.slice(0, prefix.length).toLowerCase() !== prefix) return message;

  const rest = text.slice(prefix.length);
  if (!/^\s/.test(rest)) return message;

  const body = rest.trim();
  if (body === '') return message;

  return { ...message, text: body, emotes: [], redacted: true };
}

/**
 * Characters the Redacted font can draw. Bunny serves it for Latin and Latin
 * Extended only; anything else would fall back to a READABLE font and show the
 * hidden message in plain letters. Kept narrower than the font's own range on
 * purpose - a character outside this set costs nothing but a different bar.
 */
const DRAWABLE = /^[!-~¡-ɏ]$/u;

const ENTITIES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

/**
 * SAFE HTML for a redacted message's `chat.N.html`, honouring the EmoteRenderer
 * contract in chatSlots.ts: chatter text is entity-escaped, and the only markup
 * is the span this function writes.
 *
 * An emote code such as KEKW stays as its letters, which the font draws as a
 * bar the width of the word. Every grapheme the font cannot draw (emoji,
 * Cyrillic, CJK) becomes an `x`, so it is a bar too.
 */
export function redactedHtml(text: string): string {
  const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
  let out = '';

  for (const { segment } of segmenter.segment(text)) {
    if (/^\s+$/u.test(segment)) out += ' ';
    else if (DRAWABLE.test(segment)) out += ENTITIES[segment] ?? segment;
    else out += 'x';
  }

  return `<span class="ol-redacted">${out}</span>`;
}
