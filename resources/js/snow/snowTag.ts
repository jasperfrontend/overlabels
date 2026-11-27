/**
 * The `[[[snow_scene]]]` tag and its placeholder.
 *
 * Same arrangement as `[[[plinko_board]]]` (see plinko/plinkoTag.ts) and
 * `[[[checkin_globe]]]`: a literal pre-pass over the AUTHORED template source
 * swaps the tag for a placeholder div before the block engine and the tag pass
 * run, so nothing here scans substituted output. The snow module mounts a
 * three.js canvas inside it.
 *
 * The tag is the exact literal, no pipes or arguments. Count, speed, size,
 * shape and colours are CSS custom properties on the placeholder (see
 * snowScene.ts), which is how the overlay's controls reach the snow.
 */

export const SNOW_TAG = '[[[snow_scene]]]';

export const SNOW_SELECTOR = '[data-snow-scene]';

const PLACEHOLDER = '<div class="ol-snow" data-snow-scene></div>';

export function sourceUsesSnow(source: string): boolean {
  return source.includes(SNOW_TAG);
}

export function replaceSnowTags(source: string): string {
  return source.split(SNOW_TAG).join(PLACEHOLDER);
}
