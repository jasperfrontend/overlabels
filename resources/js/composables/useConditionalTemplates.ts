/**
 * Frontend-only conditional template parser for Vue
 * Safely processes conditional logic without server-side evaluation
 */

import { blockTokenPattern, conditionPattern, MAX_NESTING_DEPTH } from '@/utils/dsl';
import { applyFormatter } from '@/utils/formatters';
import { encodeHtml, TAG_REGEX } from '@/utils/tagParser';

interface ConditionalBlock {
  type: 'if' | 'elseif' | 'else';
  condition?: string;
  content: string;
}

interface ParsedCondition {
  variable: string;
  operator?: string;
  value?: string;
  isBoolean: boolean;
}

interface ProcessOptions {
  locale?: string;
  encode?: boolean;
}

// ---------------------------------------------------------------------------
// Token-based scanner helpers (module-level, not exported)
// These replace the old regex-only approach which couldn't handle nesting.
// ---------------------------------------------------------------------------

type Tag =
  | { kind: 'if'; condition: string; index: number; length: number }
  | { kind: 'elseif'; condition: string; index: number; length: number }
  | { kind: 'else'; index: number; length: number }
  | { kind: 'endif'; index: number; length: number }
  | { kind: 'foreach'; iterable: string; alias: string; index: number; length: number }
  | { kind: 'endforeach'; index: number; length: number };

// Single regex that matches every block-control token, built from the shared
// spec (resources/dsl/dsl.json). We reset lastIndex before each call so this is
// safe to share. The condition body permits a lone `]` (spec D7) while still
// being unable to swallow the closing `]]]`.
const TOKEN_REGEX = blockTokenPattern('g');

// Matches a full condition: key, operator, value. Built from the shared spec so
// the key character class matches plain tag keys exactly - including hyphens,
// which the old inline pattern rejected (spec D5).
const CONDITION_REGEX = conditionPattern();

/**
 * Return the next token at or after `fromIndex` in string `s`, or null.
 */
function nextTag(s: string, fromIndex: number): Tag | null {
  TOKEN_REGEX.lastIndex = fromIndex;
  const m = TOKEN_REGEX.exec(s);
  if (!m) return null;

  const body = m[1];
  const idx = m.index;
  const len = m[0].length;

  if (body.startsWith('if:')) return { kind: 'if', condition: m[2].trim(), index: idx, length: len };
  if (body.startsWith('elseif:')) return { kind: 'elseif', condition: m[3].trim(), index: idx, length: len };
  if (body.startsWith('foreach:')) {
    const parts = m[4].split(/\s+as\s+/);
    return {
      kind: 'foreach',
      iterable: parts[0].trim(),
      alias: (parts[1] ?? 'item').trim(),
      index: idx,
      length: len,
    };
  }
  if (body === 'endforeach') return { kind: 'endforeach', index: idx, length: len };
  if (body === 'else') return { kind: 'else', index: idx, length: len };
  return { kind: 'endif', index: idx, length: len };
}

/**
 * Given an `if` tag, scan forward tracking depth to find its matching `[[[endif]]]`.
 * Returns the endif Tag, or null if the template is malformed (unmatched if).
 */
function findMatchingEndif(s: string, ifTag: Tag): Tag | null {
  let depth = 1; // we're already inside the if
  let pos = ifTag.index + ifTag.length;

  while (true) {
    const t = nextTag(s, pos);
    if (!t) return null; // malformed — no matching endif

    if (t.kind === 'if') depth++;
    if (t.kind === 'endif') {
      depth--;
      if (depth === 0) return t;
    }

    pos = t.index + t.length;
  }
}

/**
 * Given a `foreach` tag, scan forward tracking depth to find its matching `[[[endforeach]]]`.
 */
function findMatchingEndforeach(s: string, foreachTag: Tag): Tag | null {
  let depth = 1;
  let pos = foreachTag.index + foreachTag.length;

  while (true) {
    const t = nextTag(s, pos);
    if (!t) return null;

    if (t.kind === 'foreach') depth++;
    if (t.kind === 'endforeach') {
      depth--;
      if (depth === 0) return t;
    }

    pos = t.index + t.length;
  }
}

/**
 * Split the content between an `if` and its matching `endif` into ConditionalBlocks.
 * Only splits on `else`/`elseif` tokens at depth 0 (belonging to this if, not a nested one).
 */
function splitTopLevel(inner: string, firstCondition: string): ConditionalBlock[] {
  const blocks: ConditionalBlock[] = [];
  let depth = 0;
  let cursor = 0;
  let currentType: ConditionalBlock['type'] = 'if';
  let currentCondition: string | undefined = firstCondition;
  let pos = 0;

  while (true) {
    const t = nextTag(inner, pos);
    if (!t) break;

    pos = t.index + t.length;

    if (t.kind === 'if') {
      depth++;
    } else if (t.kind === 'endif') {
      depth--;
    } else if (depth === 0 && (t.kind === 'else' || t.kind === 'elseif')) {
      // This else/elseif belongs to the outermost block — split here
      blocks.push({ type: currentType, condition: currentCondition, content: inner.substring(cursor, t.index) });
      cursor = pos;
      currentType = t.kind;
      currentCondition = t.kind === 'elseif' ? t.condition : undefined;
    }
    // foreach/endforeach are intentionally not depth-tracked here: they
    // don't affect if/else/elseif/endif pairing.
  }

  // Push the final block (else branch or the only if branch)
  blocks.push({ type: currentType, condition: currentCondition, content: inner.substring(cursor) });

  return blocks;
}

/**
 * Resolve a possibly-dotted path against a data object.
 * Prefers a direct flat-key lookup (the shape server-side emits for most tags)
 * and falls back to walking nested objects — lets `foreach` aliases like
 * `choice.title` work alongside flat keys like `event.user_name`.
 */
function resolvePath(data: Record<string, any>, path: string): any {
  if (data == null || typeof data !== 'object') return undefined;
  // `in` rather than hasOwnProperty: a foreach scope is prototype-linked to the
  // outer payload (see buildScopedData), so the outer keys a condition inside a
  // loop needs to read are INHERITED, not own. Checking own properties only
  // would send flat dotted keys like `twitch.stream.is_live` down the
  // dot-walking fallback, where they resolve to undefined because the payload is
  // flat and there is no `twitch` object to walk into.
  if (path in data) return data[path];
  return path.split('.').reduce<any>((obj, key) => (obj == null ? undefined : obj[key]), data);
}

/**
 * Return an array for an iterable path.
 *
 * Twitch event payloads land in `data` as flattened dotted keys
 * (`event.choices.0.title`, `event.choices.count`, ...) rather than nested
 * arrays, so we synthesize an array by walking all keys prefixed by the path.
 * If the resolver already yields a real array (e.g., a nested object tree), we
 * use it directly.
 */
function resolveIterable(data: Record<string, any>, path: string): any[] {
  const direct = resolvePath(data, path);
  if (Array.isArray(direct)) return direct;

  const prefix = `${path}.`;
  const countRaw = data[`${path}.count`];
  const countFromKey = countRaw !== undefined && countRaw !== null && !isNaN(Number(countRaw)) ? Number(countRaw) : null;

  const byIndex = new Map<number, any>();

  // `for...in` rather than Object.keys: a nested foreach resolves its iterable
  // against the enclosing loop's scope, which is prototype-linked to the outer
  // payload, so the indexed keys it needs are inherited. Object.keys sees only
  // own properties and would find nothing. Object.prototype's own members are
  // non-enumerable, so they are excluded here for free.
  for (const key in data) {
    if (!key.startsWith(prefix)) continue;
    const rest = key.slice(prefix.length);
    if (rest === 'count') continue;

    const dotIdx = rest.indexOf('.');
    if (dotIdx === -1) {
      // Flat list: `event.something.0` = scalar
      if (/^\d+$/.test(rest)) {
        byIndex.set(parseInt(rest, 10), data[key]);
      }
      continue;
    }

    const indexStr = rest.slice(0, dotIdx);
    if (!/^\d+$/.test(indexStr)) continue;
    const idx = parseInt(indexStr, 10);
    const subkey = rest.slice(dotIdx + 1);

    let item = byIndex.get(idx);
    if (item === undefined || item === null || typeof item !== 'object') {
      item = {};
      byIndex.set(idx, item);
    }
    item[subkey] = data[key];
  }

  if (byIndex.size === 0) return [];

  // `count` reflects the source-of-truth size (e.g. you follow 24 channels)
  // even when the user's foreach cap limits the indexed data to fewer
  // entries. If we trusted count for iteration, the loop would pad with
  // empty `{}` for indices that have no data - visible bug when [[[raw]]]
  // dumps each item. Cap iteration at the highest populated index so the
  // loop only runs over items we actually have data for. `count` itself is
  // still available as `[[[<iterable>.count]]]` for display purposes.
  const knownMax = Math.max(...Array.from(byIndex.keys()));
  const maxIdx = countFromKey !== null ? Math.min(countFromKey - 1, knownMax) : knownMax;

  const arr: any[] = [];
  for (let i = 0; i <= maxIdx; i++) {
    arr.push(byIndex.has(i) ? byIndex.get(i) : {});
  }
  return arr;
}

/**
 * Build a scoped data object for one iteration of a foreach loop.
 *
 * Stores every value two ways: as a flat dotted key (`choice.title`) so the
 * existing flat-lookup substitution works, and as a nested structure
 * (`choice: { title }`, `loop: { index, first, last }`) so `resolvePath`
 * can walk deeper if the template writes `[[[choice.nested.field]]]`.
 *
 * The scope is PROTOTYPE-LINKED to the outer payload rather than a copy of it.
 * A loop body has to see the whole payload - a condition inside a foreach can
 * legitimately branch on whether the stream is live - and this used to be done
 * with `{ ...outer }`, once per iteration. That made the render cost quadratic:
 * doubling the item count doubled both the number of copies and the size of
 * each one, since the payload grows with the data being looped over. A 50-item
 * chat feed spent roughly three quarters of its render time copying keys it
 * never read, and the payload includes every control and list on the account
 * (OverlayTemplateController builds it wholesale), so the multiplier is the
 * user's whole account, not the template.
 *
 * Object.create() makes lookup walk the chain instead, which is O(1) to set up.
 * Own properties assigned below still shadow the outer payload, so an alias that
 * collides with an outer key wins exactly as it did before. The two places that
 * had to learn about inheritance are resolvePath (`in`, not hasOwnProperty) and
 * resolveIterable (`for...in`, not Object.keys).
 */
function buildScopedData(outer: Record<string, any>, alias: string, item: any, index: number, total: number): Record<string, any> {
  const scoped: Record<string, any> = Object.create(outer ?? null);
  const loop = {
    index,
    first: index === 0,
    last: index === total - 1,
    count: total,
  };

  // Nested handles [[[choice.deep.field]]] via resolvePath.
  scoped[alias] = item;
  scoped.loop = loop;

  // Flat dotted keys so existing lookups (conditions, substitution) work.
  scoped['loop.index'] = index;
  scoped['loop.first'] = loop.first;
  scoped['loop.last'] = loop.last;
  scoped['loop.count'] = total;

  if (item && typeof item === 'object' && !Array.isArray(item)) {
    for (const [k, v] of Object.entries(item)) {
      scoped[`${alias}.${k}`] = v;
    }
  }

  return scoped;
}

/**
 * Neutralise `[` / `]` so a `[[[...]]]` sequence that arrived as DATA cannot be
 * read as template source by the caller's outer substitution pass.
 *
 * This is the pass-1 half of the single-pass rule documented in tagParser.ts.
 * Pass 2 gets that rule for free (one `String.replace` never re-scans its own
 * output), but anything substituted here is substituted BEFORE pass 2 runs, so
 * without this a chatter typing `[[[c:kofi:total_received]]]` into a list
 * appender would have it resolved against the real payload - which carries
 * every control and list on the account, not just the ones the template names.
 *
 * Applied to values only, never to authored template text: an author writing a
 * tag in their own template is the feature, not the attack.
 *
 * Unconditional rather than "only when the value contains `[[[`", because one
 * attacker usually controls several fields of the same item, and `[` + `[[c:x]]]`
 * across two adjacent scoped tags concatenates into a live tag while neither
 * half looks dangerous alone.
 *
 * Entities rather than stripping, so nothing is silently eaten: browsers render
 * these as the literal characters, so `[AFK] brb` still reads as `[AFK] brb`.
 * The CSS sink (encode=false) gets the same treatment - it has the same pass
 * boundary, and an inert entity in a stylesheet beats a resolved tag.
 */
function defuseBrackets(value: string): string {
  return value.replace(/\[/g, '&#91;').replace(/]/g, '&#93;');
}

/**
 * Substitute scoped tokens (`alias.*`, bare `alias`, `loop.*`, bare `loop`) in
 * an already-rendered loop body. Non-scoped tokens are left alone for the
 * caller's outer substitution pass. HTML-encodes by default; honours pipe
 * formatters via the existing utility.
 */
function substituteScopedTokens(template: string, alias: string, scoped: Record<string, any>, locale: string, encode: boolean): string {
  TAG_REGEX.lastIndex = 0;
  return template.replace(TAG_REGEX, (match, key: string, pipe: string | undefined, def: string | undefined) => {
    // [[[raw]]] inside a foreach dumps the current iteration item as
    // pretty-printed JSON. Useful for inspecting the shape of an iterable
    // while writing a template. Pipe formatters are ignored.
    if (key === 'raw') {
      let json: string;
      try {
        json = JSON.stringify(scoped[alias], null, 2) ?? '';
      } catch {
        json = String(scoped[alias]);
      }
      if (encode) json = encodeHtml(json);
      return defuseBrackets(json);
    }

    const isScoped = key === alias || key.startsWith(`${alias}.`) || key === 'loop' || key.startsWith('loop.');
    // Non-scoped tags (incl. any `?? default`) are left intact for the outer
    // substitution pass, which resolves their values and defaults.
    if (!isScoped) return match;

    const val = resolvePath(scoped, key);
    const strVal = val === undefined || val === null || typeof val === 'object' ? '' : typeof val === 'boolean' ? (val ? '1' : '0') : String(val);

    // `?? default` backstops an absent scoped value, same as the outer pass.
    if (strVal === '') {
      const fallback = def?.trim();
      return fallback ? (encode ? encodeHtml(fallback) : fallback) : '';
    }

    const formatted = pipe ? applyFormatter(strVal, pipe, locale) : strVal;
    return defuseBrackets(encode ? encodeHtml(formatted) : formatted);
  });
}

// ---------------------------------------------------------------------------

export function useConditionalTemplates() {
  /**
   * Parse a condition string into its components
   */
  const parseCondition = (condition: string): ParsedCondition => {
    condition = condition.trim();

    // Check for comparison operators. The pattern comes from the shared DSL
    // spec, so variable names accept exactly what a plain tag key accepts
    // (dots, colons and hyphens) and operators are alternated longest-first.
    const comparisonMatch = condition.match(CONDITION_REGEX);
    if (comparisonMatch) {
      return {
        variable: comparisonMatch[1],
        operator: comparisonMatch[2] === '=' ? '==' : comparisonMatch[2], // Convert = to ==
        value: comparisonMatch[3].trim().replace(/^["']|["']$/g, ''), // Remove quotes if present
        isBoolean: false,
      };
    }

    // Otherwise treat as boolean
    return {
      variable: condition,
      isBoolean: true,
    };
  };

  /**
   * Evaluate a condition against provided data
   */
  const evaluateCondition = (condition: ParsedCondition, data: Record<string, any>): boolean => {
    const variableValue = resolvePath(data, condition.variable);

    // Boolean evaluation
    if (condition.isBoolean) {
      // Check for 'false', '0', null, undefined, empty string
      if (variableValue === true) return true;
      if (variableValue === false) return false;
      return !!variableValue && variableValue !== 'false' && variableValue !== '0';
    }

    // Comparison evaluation
    if (!condition.operator || condition.value === undefined) {
      return false;
    }

    // Try to parse as numbers if both sides look numeric
    const isNumericComparison = !isNaN(Number(variableValue)) && !isNaN(Number(condition.value));

    if (isNumericComparison) {
      const numValue = Number(variableValue);
      const numCompare = Number(condition.value);

      switch (condition.operator) {
        case '>':
          return numValue > numCompare;
        case '<':
          return numValue < numCompare;
        case '>=':
          return numValue >= numCompare;
        case '<=':
          return numValue <= numCompare;
        case '!=':
          return numValue !== numCompare;
        case '==':
          return numValue === numCompare;
        default:
          return false;
      }
    } else {
      // String comparison
      const strValue = String(variableValue || '');
      const strCompare = String(condition.value);

      switch (condition.operator) {
        case '==':
          return strValue === strCompare;
        case '!=':
          return strValue !== strCompare;
        // For string comparison, > and < use lexicographic ordering
        case '>':
          return strValue > strCompare;
        case '<':
          return strValue < strCompare;
        case '>=':
          return strValue >= strCompare;
        case '<=':
          return strValue <= strCompare;
        default:
          return false;
      }
    }
  };

  /**
   * Process a template string, replacing all [[[if:...]]]...[[[endif]]] and
   * [[[foreach:... as ...]]]...[[[endforeach]]] blocks.
   *
   * Uses a depth-aware token scanner so nested conditionals and loops are
   * handled correctly. Loop aliases (and `loop.*`) are substituted inside
   * each iteration so they don't leak into the outer tag-substitution pass.
   */
  const processConditionalBlocks = (template: string, data: Record<string, any>, depth: number = 0, options: ProcessOptions = {}): string => {
    if (depth > MAX_NESTING_DEPTH) {
      console.warn('Maximum conditional nesting depth reached');
      return template;
    }

    const locale = options.locale ?? 'en-US';
    const encode = options.encode ?? true;

    let out = template;
    let searchFrom = 0;

    while (true) {
      const t = nextTag(out, searchFrom);
      if (!t) break;

      if (t.kind === 'foreach') {
        const endTag = findMatchingEndforeach(out, t);
        if (!endTag) break; // malformed — abort

        const inner = out.substring(t.index + t.length, endTag.index);
        const items = resolveIterable(data, t.iterable);

        let rendered = '';
        for (let i = 0; i < items.length; i++) {
          const scoped = buildScopedData(data, t.alias, items[i], i, items.length);

          // Recurse first so nested ifs/foreaches see the scoped alias.
          let iterationOut = processConditionalBlocks(inner, scoped, depth + 1, options);
          // Resolve scoped tokens now — they can't survive into the
          // outer substitution pass because the alias won't be bound
          // there.
          iterationOut = substituteScopedTokens(iterationOut, t.alias, scoped, locale, encode);
          rendered += iterationOut;
        }

        out = out.slice(0, t.index) + rendered + out.slice(endTag.index + endTag.length);
        searchFrom = t.index + rendered.length;
        continue;
      }

      if (t.kind !== 'if') {
        // Stray else/elseif/endif/endforeach with no matching opener — skip past it
        searchFrom = t.index + t.length;
        continue;
      }

      const endifTag = findMatchingEndif(out, t);
      if (!endifTag) break; // Malformed template — abort to avoid further corruption

      // Content between [[[if:...]]] and its matching [[[endif]]]
      const inner = out.substring(t.index + t.length, endifTag.index);

      // Split inner into branches, respecting nesting depth
      const blocks = splitTopLevel(inner, t.condition);

      // Evaluate each branch in order; pick the first truthy one
      let selected = '';
      for (const block of blocks) {
        if (block.type === 'else') {
          selected = block.content;
          break;
        }
        if (block.condition) {
          const parsed = parseCondition(block.condition);
          if (evaluateCondition(parsed, data)) {
            selected = block.content;
            break;
          }
        }
      }

      // Recursively process any nested conditionals / foreaches within the selected branch
      selected = processConditionalBlocks(selected, data, depth + 1, options);

      // Splice the entire [[[if...]]]...[[[endif]]] block out, insert resolved content
      out = out.slice(0, t.index) + selected + out.slice(endifTag.index + endifTag.length);

      // Continue scanning after the inserted content (already fully processed)
      searchFrom = t.index + selected.length;
    }

    return out;
  };

  /**
   * Process a template with conditional and foreach logic.
   *
   * `options.locale` and `options.encode` are used only for resolving
   * foreach-scoped tokens (`alias.*`, `loop.*`) whose lifetime is confined to
   * the loop body. Non-scoped tokens are left for the caller's main tag
   * substitution pass.
   */
  const processTemplate = (template: string, data: Record<string, any>, options: ProcessOptions = {}): string => {
    return processConditionalBlocks(template, data, 0, options);
  };

  return {
    processTemplate,
    parseCondition,
    evaluateCondition,
  };
}
