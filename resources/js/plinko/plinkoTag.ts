/**
 * The `[[[plinko_board]]]` tag and its placeholder.
 *
 * Same arrangement as `[[[checkin_globe]]]` (see globe/globeTag.ts): a literal
 * pre-pass over the AUTHORED template source swaps the tag for a placeholder
 * div before the block engine and the tag pass run, so nothing here scans
 * substituted output. The board module mounts a canvas inside it.
 *
 * The tag is the exact literal, no pipes or arguments. Everything a template
 * can change - the command word, the number of rows, the colours - is a CSS
 * custom property on the placeholder (see plinkoBoard.ts).
 */

export const PLINKO_TAG = '[[[plinko_board]]]';

export const PLINKO_SELECTOR = '[data-plinko-board]';

const PLACEHOLDER = '<div class="ol-plinko" data-plinko-board></div>';

export function sourceUsesPlinko(source: string): boolean {
  return source.includes(PLINKO_TAG);
}

export function replacePlinkoTags(source: string): string {
  return source.split(PLINKO_TAG).join(PLACEHOLDER);
}

/** The default chat command, without its `!`. */
export const DEFAULT_PLINKO_COMMAND = 'drop';

/**
 * Is this chat message the drop command? Case-insensitive, the command word
 * must stand alone (`!drop` and `!drop please` count, `!dropped` does not),
 * and leading whitespace is ignored the way Twitch chat users type it.
 */
export function isDropCommand(text: string, command: string = DEFAULT_PLINKO_COMMAND): boolean {
  const word = command.replace(/^!/, '').trim().toLowerCase();
  if (word === '') return false;
  const match = /^\s*!(\S+)/.exec(text);
  return match !== null && match[1].toLowerCase() === word;
}
