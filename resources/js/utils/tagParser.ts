import { applyFormatter } from '@/utils/formatters';

// Matches [[[tag_name]]] and [[[tag_name|formatter]]] and [[[tag_name|formatter:args]]]
// Tag key allows word chars, dots, colons, and hyphens (for service names like overlabels-mobile)
// Pipe args allow word chars, dots, colons, hyphens, and spaces (for date patterns like dd-MM-yyyy HH:mm)
export const TAG_REGEX = /\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/g;

// HTML-encode substituted tag values so donor-supplied strings can't break out of attribute or
// text context when the result is rendered via v-html. Encodes the five chars that matter for
// HTML/attribute contexts. CSS output path skips this because style.textContent is not HTML-parsed.
export function encodeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function replaceTagsWithFormatting(
  source: string,
  sourceData: Record<string, unknown>,
  locale: string,
  encode: boolean = true,
): string {
  return source.replace(TAG_REGEX, (_match, key: string, pipe: string | undefined) => {
    const val = sourceData[key];
    if (val === undefined || val === null || typeof val === 'object') return '';
    const strVal = String(val);
    const formatted = pipe ? applyFormatter(strVal, pipe, locale) : strVal;
    return encode ? encodeHtml(formatted) : formatted;
  });
}
