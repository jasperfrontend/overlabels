/**
 * Frontend-only conditional template parser for Vue
 * Safely processes conditional logic without server-side evaluation
 */

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

// ---------------------------------------------------------------------------
// Token-based scanner helpers (module-level, not exported)
// These replace the old regex-only approach which couldn't handle nesting.
// ---------------------------------------------------------------------------

type Tag =
    | { kind: 'if'; condition: string; index: number; length: number }
    | { kind: 'elseif'; condition: string; index: number; length: number }
    | { kind: 'else'; index: number; length: number }
    | { kind: 'endif'; index: number; length: number };

// Single regex that matches all four token types.
// We reset lastIndex before each call so this is safe to share.
const TOKEN_REGEX = /\[\[\[(if:([^\]]+)|elseif:([^\]]+)|else|endif)]]]/g;

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
    if (body === 'else')            return { kind: 'else',   index: idx, length: len };
    return                                 { kind: 'endif',  index: idx, length: len };
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
    }

    // Push the final block (else branch or the only if branch)
    blocks.push({ type: currentType, condition: currentCondition, content: inner.substring(cursor) });

    return blocks;
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
        const variableValue = data[condition.variable];

        // Boolean evaluation
        if (condition.isBoolean) {
            // Check for 'false', '0', null, undefined, empty string
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
     * Process a template string, replacing all [[[if:...]]]...[[[endif]]] blocks
     * with the content of the first branch whose condition is true.
     *
     * Uses a depth-aware token scanner so nested conditionals are handled correctly.
     */
    const processConditionalBlocks = (template: string, data: Record<string, any>, depth: number = 0): string => {
        if (depth > 10) {
            console.warn('Maximum conditional nesting depth reached');
            return template;
        }

        let out = template;
        let searchFrom = 0;

        while (true) {
            const t = nextTag(out, searchFrom);
            if (!t) break;

            if (t.kind !== 'if') {
                // Stray else/elseif/endif with no matching if — skip past it
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

            // Recursively process any nested conditionals within the selected branch
            selected = processConditionalBlocks(selected, data, depth + 1);

            // Splice the entire [[[if...]]]...[[[endif]]] block out, insert resolved content
            out = out.slice(0, t.index) + selected + out.slice(endifTag.index + endifTag.length);

            // Continue scanning after the inserted content (already fully processed)
            searchFrom = t.index + selected.length;
        }

        return out;
    };

    /**
     * Process a template with conditional logic
     */
    const processTemplate = (template: string, data: Record<string, any>): string => {
        return processConditionalBlocks(template, data);
    };

    return {
        processTemplate,
        parseCondition,
        evaluateCondition
    };
}
