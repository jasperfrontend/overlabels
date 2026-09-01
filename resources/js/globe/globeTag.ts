/**
 * The `[[[checkin_globe]]]` tag and its placeholder.
 *
 * A normal data-driven tag cannot emit markup: replaceTagsWithFormatting
 * renders a valueless tag as '' and HTML-encodes any value it had. So the
 * globe tag is replaced by a literal pre-pass over the AUTHORED template
 * source, before the block engine and the tag pass run. That does not touch
 * the never-reparse rule - blocks and tags only ever see the placeholder
 * div, and nothing here scans substituted output.
 *
 * The tag is the exact literal, no pipes or arguments; styling belongs to
 * CSS custom properties on the placeholder (see checkinGlobe.ts).
 */

export const GLOBE_TAG = '[[[checkin_globe]]]';

export const GLOBE_SELECTOR = '[data-checkin-globe]';

const PLACEHOLDER = '<div class="ol-checkin-globe" data-checkin-globe></div>';

export function sourceUsesGlobe(source: string): boolean {
  return source.includes(GLOBE_TAG);
}

export function replaceGlobeTags(source: string): string {
  return source.split(GLOBE_TAG).join(PLACEHOLDER);
}
