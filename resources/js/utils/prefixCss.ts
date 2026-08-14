/**
 * Prefix-scope a block's CSS so two blocks styling the same class name can't
 * collide on a composed Builder overlay. Pure, dependency-free.
 *
 * Rules:
 * - Plain rules: every comma-separated selector gets the scope prepended as a
 *   descendant combinator (`.label` -> `#blk-a3 .label`).
 * - `:root`, `html`, `body` (as the leading compound) are REPLACED by the scope
 *   itself, so block-level custom properties and backgrounds attach to the
 *   block's wrapper instead of leaking page-wide.
 * - `@media` / `@supports` / `@container` / `@layer` (with a body of rules)
 *   recurse into their body.
 * - `@keyframes`, `@font-face`, `@import`, `@property`, `@charset` pass through
 *   untouched.
 *
 * Documented limitations (also in the block author docs):
 * - Keyframe names are NOT renamed: two DIFFERENT blocks both defining
 *   `@keyframes pulse` collide (last one wins). Two instances of the SAME
 *   block emit identical keyframes, which is harmless.
 * - Native CSS nesting is treated as opaque rule-body content - only the
 *   top-level selector of a nested tree gets scoped. Block authors should
 *   write flat CSS.
 * - Exotic selectors (`html.dark body.compact .x`) degrade gracefully: only
 *   the first `:root`/`html`/`body` compound is rewritten.
 */

const RECURSE_AT_RULES = /^@(media|supports|container|layer)\b/;
const PASSTHROUGH_AT_RULES = /^@/;
const ROOT_COMPOUND = /^(:root|html|body)(?![\w-])/;

export function prefixCss(css: string, scope: string): string {
  const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '');
  return prefixRules(stripped, scope).trim();
}

function prefixRules(css: string, scope: string): string {
  let out = '';
  let i = 0;

  while (i < css.length) {
    const braceOpen = css.indexOf('{', i);
    if (braceOpen === -1) {
      out += css.slice(i);
      break;
    }

    const selector = css.slice(i, braceOpen).trim();
    const bodyEnd = findMatchingBrace(css, braceOpen);
    if (bodyEnd === -1) {
      // Unbalanced braces - emit the rest verbatim rather than eating it.
      out += css.slice(i);
      break;
    }
    const body = css.slice(braceOpen + 1, bodyEnd);

    if (RECURSE_AT_RULES.test(selector)) {
      out += `${selector} {\n${prefixRules(body, scope)}\n}\n`;
    } else if (PASSTHROUGH_AT_RULES.test(selector)) {
      out += `${selector} {${body}}\n`;
    } else if (selector) {
      const scoped = selector
        .split(',')
        .map((sel) => scopeSelector(sel.trim(), scope))
        .filter(Boolean)
        .join(', ');
      out += `${scoped} {${body}}\n`;
    }

    i = bodyEnd + 1;
  }

  return out;
}

function scopeSelector(selector: string, scope: string): string {
  if (!selector) return '';

  if (ROOT_COMPOUND.test(selector)) {
    // `:root` -> scope; `body .x` -> `scope .x`; `body.foo` -> `scope.foo`
    return scope + selector.replace(ROOT_COMPOUND, '');
  }

  return `${scope} ${selector}`;
}

function findMatchingBrace(css: string, openIndex: number): number {
  let depth = 0;
  for (let i = openIndex; i < css.length; i++) {
    if (css[i] === '{') depth++;
    else if (css[i] === '}') {
      depth--;
      if (depth === 0) return i;
    }
  }
  return -1;
}
