import { applyFormatter } from '@/utils/formatters';

// Matches [[[tag_name]]] and [[[tag_name|formatter]]] and [[[tag_name|formatter:args]]]
// Tag key allows word chars, dots, colons, and hyphens (for service names like overlabels-mobile)
// Pipe args allow word chars, dots, colons, hyphens, and spaces (for date patterns like dd-MM-yyyy HH:mm)
//
// SINGLE-PASS BY DESIGN: this regex runs exactly once per render. Substituted values are never
// re-scanned for tags. This is the day-one rule that prevents template-injection via user content
// (donor names, chat messages, control values containing [[[c:foo]]] etc).
//
// Canonical adversarial test: a control "scr" with value "scr" and "scr_end" with value "/scr",
// composed in a template as `<[[[c:scr]]]ipt>alert('hi')<[[[c:scr_end]]][[[c:ipt]]]>`, resolves
// to the literal string `<script>alert('hi')</script>` AFTER this pass completes. Since there is
// no second pass and the result is not re-parsed as a template, the string remains inert text
// (and encodeHtml below additionally neutralises it for v-html sinks). If you ever feel tempted
// to add a "just one more pass" loop here, this comment is why you won't.
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
