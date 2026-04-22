/**
 * Frontend-only conditional template parser for Vue
 * Safely processes conditional logic without server-side evaluation
 */

import { applyFormatter } from '@/utils/formatters';
import { TAG_REGEX, encodeHtml } from '@/utils/tagParser';

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

// Single regex that matches every block-control token.
// We reset lastIndex before each call so this is safe to share.
const TOKEN_REGEX = /\[\[\[(if:([^\]]+)|elseif:([^\]]+)|else|endif|foreach:([^\]]+)|endforeach)]]]/g;

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

    if (body.startsWith('if:'))     return { kind: 'if',     condition: m[2].trim(), index: idx, length: len };
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
    if (body === 'else')       return { kind: 'else',       index: idx, length: len };
    return                            { kind: 'endif',      index: idx, length: len };
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
    if (data == null) return undefined;
    if (Object.prototype.hasOwnProperty.call(data, path)) return data[path];
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
    const countFromKey = countRaw !== undefined && countRaw !== null && !isNaN(Number(countRaw))
        ? Number(countRaw)
        : null;

    const byIndex = new Map<number, any>();

    for (const key of Object.keys(data)) {
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

    if (byIndex.size === 0 && countFromKey === null) return [];

    const maxIdx = countFromKey !== null
        ? countFromKey - 1
        : Math.max(-1, ...Array.from(byIndex.keys()));

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
 */
function buildScopedData(
    outer: Record<string, any>,
    alias: string,
    item: any,
    index: number,
    total: number,
): Record<string, any> {
    const scoped: Record<string, any> = { ...outer };
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
 * Substitute scoped tokens (`alias.*`, bare `alias`, `loop.*`, bare `loop`) in
 * an already-rendered loop body. Non-scoped tokens are left alone for the
 * caller's outer substitution pass. HTML-encodes by default; honours pipe
 * formatters via the existing utility.
 */
function substituteScopedTokens(
    template: string,
    alias: string,
    scoped: Record<string, any>,
    locale: string,
    encode: boolean,
): string {
    TAG_REGEX.lastIndex = 0;
    return template.replace(TAG_REGEX, (match, key: string, pipe: string | undefined) => {
        const isScoped =
            key === alias ||
            key.startsWith(`${alias}.`) ||
            key === 'loop' ||
            key.startsWith('loop.');
        if (!isScoped) return match;

        const val = resolvePath(scoped, key);
        if (val === undefined || val === null || typeof val === 'object') return '';

        const strVal = typeof val === 'boolean' ? (val ? '1' : '0') : String(val);
        const formatted = pipe ? applyFormatter(strVal, pipe, locale) : strVal;
        return encode ? encodeHtml(formatted) : formatted;
    });
}

// ---------------------------------------------------------------------------

export function useConditionalTemplates() {
    /**
     * Parse a condition string into its components
     */
    const parseCondition = (condition: string): ParsedCondition => {
        condition = condition.trim();

        // Check for comparison operators
        // Updated regex to support dots and colons in variable names like event.bits, c:deaths
        const comparisonMatch = condition.match(/^([a-zA-Z0-9_.:]+)\s*(>=|<=|>|<|!=|=)\s*(.+)$/);
        if (comparisonMatch) {
            return {
                variable: comparisonMatch[1],
                operator: comparisonMatch[2] === '=' ? '==' : comparisonMatch[2], // Convert = to ==
                value: comparisonMatch[3].trim().replace(/^["']|["']$/g, ''), // Remove quotes if present
                isBoolean: false
            };
        }

        // Otherwise treat as boolean
        return {
            variable: condition,
            isBoolean: true
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
                case '>': return numValue > numCompare;
                case '<': return numValue < numCompare;
                case '>=': return numValue >= numCompare;
                case '<=': return numValue <= numCompare;
                case '!=': return numValue !== numCompare;
                case '==': return numValue === numCompare;
                default: return false;
            }
        } else {
            // String comparison
            const strValue = String(variableValue || '');
            const strCompare = String(condition.value);

            switch (condition.operator) {
                case '==': return strValue === strCompare;
                case '!=': return strValue !== strCompare;
                // For string comparison, > and < use lexicographic ordering
                case '>': return strValue > strCompare;
                case '<': return strValue < strCompare;
                case '>=': return strValue >= strCompare;
                case '<=': return strValue <= strCompare;
                default: return false;
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
    const processConditionalBlocks = (
        template: string,
        data: Record<string, any>,
        depth: number = 0,
        options: ProcessOptions = {},
    ): string => {
        if (depth > 10) {
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
    const processTemplate = (
        template: string,
        data: Record<string, any>,
        options: ProcessOptions = {},
    ): string => {
        return processConditionalBlocks(template, data, 0, options);
    };

    return {
        processTemplate,
        parseCondition,
        evaluateCondition
    };
}
