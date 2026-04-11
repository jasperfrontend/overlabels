/**
 * Frontend-only expression engine for overlay controls.
 *
 * Uses jsep to parse user expressions into an AST, then evaluates them
 * against a context built from the reactive data store. Vue's reactivity
 * handles the cascade automatically - when a control value changes, any
 * expression that references it re-evaluates.
 *
 * Security: construction sandboxes the evaluator. It only handles
 * a whitelist of AST node types, uses Object.create(null) for context
 * objects, and blocks prototype chain access. No eval / new Function.
 */

import jsep from 'jsep';
import { type Ref, watchEffect, type WatchStopHandle } from 'vue';

// Blocked property names to prevent prototype pollution
const BLOCKED_PROPS = new Set(['__proto__', 'constructor', 'prototype']);

// Allowed binary operators
const BINARY_OPS: Record<string, (l: unknown, r: unknown) => unknown> = {
  '+': (l, r) => {
    if (typeof l === 'number' && typeof r === 'number') return l + r;
    return String(l ?? '') + String(r ?? '');
  },
  '-': (l, r) => toNum(l) - toNum(r),
  '*': (l, r) => toNum(l) * toNum(r),
  '/': (l, r) => {
    const d = toNum(r);
    return d === 0 ? 0 : toNum(l) / d;
  },
  '%': (l, r) => {
    const d = toNum(r);
    return d === 0 ? 0 : toNum(l) % d;
  },
  '==': (l, r) => coerceCompare(l, r, (a, b) => a === b),
  '!=': (l, r) => coerceCompare(l, r, (a, b) => a !== b),
  '>': (l, r) => coerceCompare(l, r, (a, b) => a > b),
  '<': (l, r) => coerceCompare(l, r, (a, b) => a < b),
  '>=': (l, r) => coerceCompare(l, r, (a, b) => a >= b),
  '<=': (l, r) => coerceCompare(l, r, (a, b) => a <= b),
  '&&': (l, r) => isTruthy(l) && isTruthy(r),
  '||': (l, r) => isTruthy(l) || isTruthy(r),
};

// Allowed unary operators
const UNARY_OPS: Record<string, (v: unknown) => unknown> = {
  '-': (v) => -toNum(v),
  '+': (v) => toNum(v),
  '!': (v) => !isTruthy(v),
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function toNum(v: unknown): number {
  if (typeof v === 'number') return v;
  if (typeof v === 'boolean') return v ? 1 : 0;
  if (v === null || v === undefined || v === '') return 0;
  const n = Number(v);
  return isNaN(n) ? 0 : n;
}

function isTruthy(v: unknown): boolean {
  if (v === 0 || v === '' || v === null || v === undefined || v === false) return false;
  if (typeof v === 'number' && isNaN(v)) return false;
  return !(v === '0' || v === 'false');

}

/** Numeric coercion for comparisons - match existing useConditionalTemplates behavior */
function coerceCompare(l: unknown, r: unknown, fn: (a: number | string, b: number | string) => boolean): boolean {
  const lStr = String(l ?? '');
  const rStr = String(r ?? '');
  if (isNumericString(lStr) && isNumericString(rStr)) {
    return fn(Number(lStr), Number(rStr));
  }
  return fn(lStr, rStr);
}

function isNumericString(s: string): boolean {
  return s !== '' && !isNaN(Number(s));
}

// ---------------------------------------------------------------------------
// Context builder: flat data.value -> nested { c: { ... } } with coercion
// ---------------------------------------------------------------------------

export function buildContext(data: Record<string, unknown>): Record<string, unknown> {
  const c: Record<string, unknown> = Object.create(null);

  for (const key in data) {
    if (!key.startsWith('c:')) continue;

    const rawKey = key.slice(2); // strip "c:" prefix
    const rawVal = data[key];
    const val = coerceValue(rawVal);

    // Check for namespaced keys like "kofi:kofis_received"
    const colonIdx = rawKey.indexOf(':');
    if (colonIdx !== -1) {
      const namespace = rawKey.slice(0, colonIdx);
      const subKey = rawKey.slice(colonIdx + 1);

      if (BLOCKED_PROPS.has(namespace) || BLOCKED_PROPS.has(subKey)) continue;

      // If a scalar value already occupies this namespace (e.g. c:timer = "42"),
      // don't clobber it with a namespace object from c:timer:running.
      const existing = c[namespace];
      if (existing !== undefined && existing !== null && typeof existing !== 'object') {
        continue;
      }

      // Create or reuse the namespace object
      if (existing === undefined || existing === null) {
        c[namespace] = Object.create(null);
      }
      (c[namespace] as Record<string, unknown>)[subKey] = val;
    } else {
      if (BLOCKED_PROPS.has(rawKey)) continue;
      // Overwrite any existing namespace object - scalar keys take priority
      c[rawKey] = val;
    }
  }

  const ctx: Record<string, unknown> = Object.create(null);
  ctx['c'] = c;
  return ctx;
}

/** Coerce string values to numbers where possible */
function coerceValue(raw: unknown): unknown {
  if (raw === null || raw === undefined) return raw;
  if (typeof raw === 'number' || typeof raw === 'boolean') return raw;
  if (typeof raw !== 'string') return raw;
  if (raw === '') return raw;
  const n = Number(raw);
  return isNaN(n) ? raw : n;
}

// ---------------------------------------------------------------------------
// Built-in functions (CallExpression support)
// ---------------------------------------------------------------------------

/** Coerce a value to a comparable number - handles numeric strings and ISO dates. */
function toComparable(v: unknown): number {
  if (typeof v === 'number') return v;
  if (v === null || v === undefined || v === '') return -Infinity;
  const s = String(v);
  const n = Number(s);
  if (!isNaN(n)) return n;
  // Try ISO date string
  const ms = Date.parse(s);
  return isNaN(ms) ? -Infinity : ms;
}

/**
 * argmax(value1, label1, value2, label2, ...) - returns the label paired with the highest value.
 * argmin works identically but picks the lowest value.
 * Ties: first pair wins.
 */
function argExtreme(args: unknown[], mode: 'max' | 'min'): unknown {
  if (args.length === 0) return '';
  if (args.length % 2 !== 0) return '⚠ Odd argument count - needs value, label pairs';

  let bestVal = toComparable(args[0]);
  let bestLabel = args[1];

  for (let i = 2; i < args.length; i += 2) {
    const val = toComparable(args[i]);
    if (mode === 'max' ? val > bestVal : val < bestVal) {
      bestVal = val;
      bestLabel = args[i + 1];
    }
  }
  return bestLabel;
}

type FnImpl = (args: unknown[]) => unknown;

/** Names of arg-family functions that require even argument counts (value, label pairs). */
export const ARG_FUNCTIONS = new Set(['argmax', 'argmin', 'latest', 'oldest']);

/** All supported function names. */
export const SUPPORTED_FUNCTIONS = new Set([
  'argmax', 'argmin', 'latest', 'oldest',
  'max', 'min', 'sum', 'avg', 'abs', 'round', 'floor', 'ceil',
  'now',
]);

const FUNCTIONS: Record<string, FnImpl> = {
  // arg* family - return the label paired with the winning value
  argmax: (args) => argExtreme(args, 'max'),
  argmin: (args) => argExtreme(args, 'min'),
  latest: (args) => argExtreme(args, 'max'),
  oldest: (args) => argExtreme(args, 'min'),

  // Scalar math
  max: (args) => Math.max(...args.map(toNum)),
  min: (args) => Math.min(...args.map(toNum)),
  sum: (args) => args.reduce((acc: number, v) => acc + toNum(v), 0),
  avg: (args) => args.length === 0 ? 0 : args.reduce((acc: number, v) => acc + toNum(v), 0) / args.length,
  abs: (args) => Math.abs(toNum(args[0])),
  round: (args) => Math.round(toNum(args[0])),
  floor: (args) => Math.floor(toNum(args[0])),
  ceil: (args) => Math.ceil(toNum(args[0])),

  // Time (Unix epoch seconds, matching _at companion values)
  now: () => Math.floor(Date.now() / 1000),
};

// ---------------------------------------------------------------------------
// AST evaluator - recursive tree walker
// ---------------------------------------------------------------------------

export function evaluate(node: jsep.Expression, ctx: Record<string, unknown>): unknown {
  switch (node.type) {
    case 'Literal':
      return (node as jsep.Literal).value;

    case 'Identifier':
      return ctx[(node as jsep.Identifier).name];

    case 'MemberExpression': {
      const me = node as jsep.MemberExpression;
      const obj = evaluate(me.object, ctx);
      if (obj === null || obj === undefined || typeof obj !== 'object') return undefined;

      let prop: string;
      if (me.computed) {
        // c["key"] bracket notation
        const propVal = evaluate(me.property, ctx);
        prop = String(propVal ?? '');
      } else {
        // c.key dot notation
        prop = (me.property as jsep.Identifier).name;
      }

      if (BLOCKED_PROPS.has(prop)) return undefined;
      if (!Object.prototype.hasOwnProperty.call(obj, prop)) return undefined;

      return (obj as Record<string, unknown>)[prop];
    }

    case 'BinaryExpression': {
      const be = node as jsep.BinaryExpression;
      const op = BINARY_OPS[be.operator];
      if (!op) return undefined;

      // Short-circuit for logical operators
      if (be.operator === '&&') {
        const left = evaluate(be.left, ctx);
        return isTruthy(left) ? evaluate(be.right, ctx) : left;
      }
      if (be.operator === '||') {
        const left = evaluate(be.left, ctx);
        return isTruthy(left) ? left : evaluate(be.right, ctx);
      }

      return op(evaluate(be.left, ctx), evaluate(be.right, ctx));
    }

    case 'UnaryExpression': {
      const ue = node as jsep.UnaryExpression;
      const op = UNARY_OPS[ue.operator];
      if (!op) return undefined;
      return op(evaluate(ue.argument, ctx));
    }

    case 'ConditionalExpression': {
      const ce = node as jsep.ConditionalExpression;
      return isTruthy(evaluate(ce.test, ctx))
        ? evaluate(ce.consequent, ctx)
        : evaluate(ce.alternate, ctx);
    }

    case 'CallExpression': {
      const ce = node as jsep.CallExpression;
      // Only allow simple function names (Identifier), not computed calls
      if (ce.callee.type !== 'Identifier') return undefined;
      const fnName = (ce.callee as jsep.Identifier).name;
      const fn = FUNCTIONS[fnName];
      if (!fn) return undefined;
      const args = ce.arguments.map((arg) => evaluate(arg, ctx));
      return fn(args);
    }

    // All other node types (ArrayExpression, ThisExpression, Compound, etc.)
    // are intentionally unsupported for security.
    default:
      return undefined;
  }
}

// ---------------------------------------------------------------------------
// Stringify result for storage in data.value
// ---------------------------------------------------------------------------

function resultToString(val: unknown): string {
  if (val === null || val === undefined) return '';
  if (typeof val === 'boolean') return val ? '1' : '0';
  if (typeof val === 'number') {
    if (isNaN(val) || !isFinite(val)) return '0';
    return String(val);
  }
  return String(val);
}

// ---------------------------------------------------------------------------
// Composable
// ---------------------------------------------------------------------------

interface ExpressionEntry {
  key: string; // broadcast key, e.g. "total_donations"
  dataKey: string; // data.value key, e.g. "c:total_donations"
  expression: string;
  ast: jsep.Expression | null;
  stop: WatchStopHandle | null;
}


export function useExpressionEngine(data: Ref<Record<string, any> | null | undefined>) {
  const registry = new Map<string, ExpressionEntry>();

  function registerExpression(key: string, expression: string): void {
    // Clean up any existing registration for this key
    unregisterExpression(key);

    const dataKey = `c:${key}`;

    // Parse the expression once
    let ast: jsep.Expression | null = null;
    try {
      ast = jsep(expression);
    } catch (e) {
      console.warn(`[ExpressionEngine] Parse error for "${key}":`, e);
    }

    // Set up reactive evaluation via watchEffect
    const stop = watchEffect(() => {
      if (!data.value || !ast) return;

      // Reading data.value properties here registers Vue reactive dependencies.
      // When any referenced control changes, this effect re-runs automatically.
      const ctx = buildContext(data.value);

      try {
        const result = evaluate(ast, ctx);
        const strResult = resultToString(result);

        // Only write back if value actually changed, to avoid infinite loops
        if (data.value[dataKey] !== strResult) {
          data.value = { ...data.value, [dataKey]: strResult };
        }
      } catch (e) {
        console.warn(`[ExpressionEngine] Evaluation error for "${key}":`, e);
      }
    });

    registry.set(key, { key, dataKey, expression, ast, stop });
  }

  function unregisterExpression(key: string): void {
    const entry = registry.get(key);
    if (entry?.stop) {
      entry.stop();
    }
    registry.delete(key);
  }

  function destroy(): void {
    for (const entry of registry.values()) {
      if (entry.stop) entry.stop();
    }
    registry.clear();
  }

  return {
    registerExpression,
    unregisterExpression,
    destroy,
  };
}
