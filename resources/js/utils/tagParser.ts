import { applyFormatter } from '@/utils/formatters';

// Matches [[[tag]]], [[[tag|formatter]]], [[[tag|formatter:args]]], and an optional
// trailing `?? default` slot: [[[tag ?? fallback]]] / [[[tag|formatter ?? fallback]]].
// - Group 1 (tag key): word chars, dots, colons, hyphens (legacy hyphenated service names)
// - Group 2 (pipe args, optional): word chars, dots, colons, hyphens, spaces (date patterns like dd-MM-yyyy HH:mm)
// - Group 3 (default, optional): the literal text after `??`, captured lazily up to the
//   closing `]]]`. May contain spaces/punctuation; the only thing it can't contain is `]]]`.
//
// SINGLE-PASS BY DESIGN: this regex runs exactly once per render. Substituted values are never
// re-scanned for tags. This is the day-one rule that prevents template-injection via user content
// (donor names, chat messages, control values containing [[[c:foo]]] etc). The `?? default` is
// part of the AUTHORED template, not substituted data, so it inherits the same single-pass safety:
// it is emitted verbatim and never re-parsed.
//
// Canonical adversarial test: a control "scr" with value "scr" and "scr_end" with value "/scr",
// composed in a template as `<[[[c:scr]]]ipt>alert('hi')<[[[c:scr_end]]][[[c:ipt]]]>`, resolves
// to the literal string `<script>alert('hi')</script>` AFTER this pass completes. Since there is
// no second pass and the result is not re-parsed as a template, the string remains inert text
// (and encodeHtml below additionally neutralises it for v-html sinks). If you ever feel tempted
// to add a "just one more pass" loop here, this comment is why you won't.
export const TAG_REGEX = /\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?(?:\s*\?\?\s*(.*?))?]]]/g;

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
  return source.replace(
    TAG_REGEX,
    (_match, key: string, pipe: string | undefined, def: string | undefined) => {
      const val = sourceData[key];
      const missing = val === undefined || val === null || typeof val === 'object';
      const strVal = missing ? '' : String(val);

      // `?? default` backstops ABSENCE only: an empty value renders the literal
      // default (verbatim, no pipe). A present-but-unexpected value is never
      // "absent", so the default never fires for it. The default is HTML-encoded
      // here just like any value, so it can't break out of an HTML/v-html sink.
      if (strVal === '') {
        const fallback = def?.trim();
        return fallback ? (encode ? encodeHtml(fallback) : fallback) : '';
      }

      const formatted = pipe ? applyFormatter(strVal, pipe, locale) : strVal;
      return encode ? encodeHtml(formatted) : formatted;
    },
  );
}

// ---------------------------------------------------------------------------
// CSS custom-property fast path (Phase 1 of surgical patching).
//
// Rewrites every [[[tag|pipe]]] inside a CSS source into a `var(--ol-...)`
// reference. The renderer injects the rewritten stylesheet exactly once at
// mount; subsequent tag updates write to CSS custom properties via
// document.documentElement.style.setProperty, avoiding the full re-parse +
// <style> swap that the slow path triggers on every data update.
//
// Bails to the slow path if the CSS contains conditional or foreach blocks,
// since those select between subtrees of text and can't be reduced to a
// single var() reference.
// ---------------------------------------------------------------------------

export type CssBinding = { property: string; pipe?: string; suffix?: string };
export type CssBindingTable = Map<string, CssBinding[]>;

export type CompiledCssBindings =
  | { fastPath: true; css: string; bindings: CssBindingTable }
  | { fastPath: false };

// Characters that mark a safe boundary between a placeholder and surrounding
// CSS. Anything outside this set adjacent to `[[[` on the left would have to
// be folded into the var()'s value at write time (e.g. `#[[[hex]]]` needs `#`
// included with the resolved value because `#var(--x)` is not valid CSS); we
// punt on that in Phase 1 and bail to the slow path instead.
const CSS_BOUNDARY = /[\s;,(){}:/*"'=!]/;

export function compileCssBindings(source: string): CompiledCssBindings {
  if (
    source.includes('[[[if:') ||
    source.includes('[[[elseif:') ||
    source.includes('[[[foreach:')
  ) {
    return { fastPath: false };
  }

  type Match = {
    index: number;
    consumed: number;
    key: string;
    pipe?: string;
    suffix: string;
  };

  const matches: Match[] = [];
  for (const m of source.matchAll(TAG_REGEX)) {
    const start = m.index ?? 0;
    const placeholderLen = m[0].length;
    const key = m[1];
    const pipe = m[2];

    // A `?? default` fallback can't be collapsed into a single var() reference
    // (the fallback is conditional on emptiness, resolved per-update). Hand the
    // whole source to the slow path, which applies defaults inline.
    if (m[3] !== undefined) {
      return { fastPath: false };
    }

    // Left-side adjacency check: `--x: [[[k]]]` is fine (space before),
    // `#[[[hex]]]` is not (hex digit prefix would need merging).
    if (start > 0) {
      const prev = source[start - 1];
      if (!CSS_BOUNDARY.test(prev)) {
        return { fastPath: false };
      }
    }

    // Right-side suffix: capture letters and `%` glued to `]]]`. Covers
    // `[[[x]]]px`, `[[[x]]]%`, `[[[c:r|round:0]]]vh`, etc. The browser will
    // not accept `var(--x)px`, so we have to bake the unit into the property
    // value at write time.
    const suffixMatch = source.slice(start + placeholderLen).match(/^[a-zA-Z%]+/);
    const suffix = suffixMatch ? suffixMatch[0] : '';

    matches.push({
      index: start,
      consumed: placeholderLen + suffix.length,
      key,
      pipe,
      suffix,
    });
  }

  const bindings: CssBindingTable = new Map();
  // Property names are keyed by (key, pipe, suffix) so the same tag emitted
  // with two different unit suffixes (`[[[x]]]px` vs `[[[x]]]%`) resolves to
  // two distinct properties with the unit pre-baked.
  const propertyByHash = new Map<string, string>();
  const usedProperties = new Set<string>();

  function getOrCreateProperty(hash: string, displayPart: string): string {
    const existing = propertyByHash.get(hash);
    if (existing) return existing;

    let base = displayPart
      .replace(/[^a-z0-9_]/gi, '-')
      .replace(/-+/g, '-')
      .toLowerCase();
    if (base.startsWith('-')) base = base.slice(1);
    if (base.endsWith('-')) base = base.slice(0, -1);

    let candidate = `--ol-${base}`;
    let i = 1;
    while (usedProperties.has(candidate)) {
      candidate = `--ol-${base}-${i++}`;
    }
    usedProperties.add(candidate);
    propertyByHash.set(hash, candidate);
    return candidate;
  }

  let css = '';
  let cursor = 0;
  for (const match of matches) {
    css += source.slice(cursor, match.index);

    const hash = `${match.key}|${match.pipe ?? ''}|${match.suffix}`;
    const displayParts = [match.key];
    if (match.pipe) displayParts.push(match.pipe);
    if (match.suffix) displayParts.push(match.suffix);
    const property = getOrCreateProperty(hash, displayParts.join('-'));

    const list = bindings.get(match.key) ?? [];
    if (!list.some((b) => b.property === property)) {
      list.push({ property, pipe: match.pipe, suffix: match.suffix || undefined });
    }
    bindings.set(match.key, list);

    css += `var(${property})`;
    cursor = match.index + match.consumed;
  }
  css += source.slice(cursor);

  return { fastPath: true, css, bindings };
}
