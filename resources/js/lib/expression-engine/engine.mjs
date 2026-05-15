/**
 * Shared expression engine for Overlabels Expression Controls.
 *
 * Single source of truth - imported by both:
 *   - resources/js/composables/useExpressionEngine.ts (frontend overlay)
 *   - services/expression-engine/index.mjs (Node sidecar, server-side eval)
 *
 * Pure functions only. No Vue, no DOM, no I/O. Takes (expressionString,
 * context) -> stringified value. Context shape:
 *   {
 *     c: { foo: 5, kofi: { donations: 42, latest_donor: "alice" } },
 *     t: { followers_total: 100 },
 *     PI: 3.14159...
 *   }
 *
 * Security: no eval / new Function. AST walk with a whitelist of node types.
 * Object.create(null) for context objects. Prototype-chain props blocked.
 */

import jsep from 'jsep';

// Blocked property names to prevent prototype pollution
const BLOCKED_PROPS = new Set(['__proto__', 'constructor', 'prototype']);

// Allowed binary operators
const BINARY_OPS = {
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
const UNARY_OPS = {
  '-': (v) => -toNum(v),
  '+': (v) => toNum(v),
  '!': (v) => !isTruthy(v),
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

export function toNum(v) {
  if (typeof v === 'number') return v;
  if (typeof v === 'boolean') return v ? 1 : 0;
  if (v === null || v === undefined || v === '') return 0;
  const n = Number(v);
  return isNaN(n) ? 0 : n;
}

export function isTruthy(v) {
  if (v === 0 || v === '' || v === null || v === undefined || v === false) return false;
  if (typeof v === 'number' && isNaN(v)) return false;
  return !(v === '0' || v === 'false');
}

/** Numeric coercion for comparisons - match existing useConditionalTemplates behavior */
function coerceCompare(l, r, fn) {
  const lStr = String(l ?? '');
  const rStr = String(r ?? '');
  if (isNumericString(lStr) && isNumericString(rStr)) {
    return fn(Number(lStr), Number(rStr));
  }
  return fn(lStr, rStr);
}

function isNumericString(s) {
  return s !== '' && !isNaN(Number(s));
}

// ---------------------------------------------------------------------------
// Context builder: flat data -> nested { c: { ... }, t: { ... } } with coercion
// ---------------------------------------------------------------------------

/**
 * Extract every key in `data` that starts with `prefix`, strip the prefix, and
 * build a nested object. Supports one level of `namespace:subKey` nesting
 * (matching how `c:kofi:donations_received` expands to `c.kofi.donations_received`).
 */
function extractNamespace(data, prefix) {
  const ns = Object.create(null);
  const prefixLen = prefix.length;

  for (const key in data) {
    if (!key.startsWith(prefix)) continue;

    const rawKey = key.slice(prefixLen);
    const val = coerceValue(data[key]);

    const colonIdx = rawKey.indexOf(':');
    if (colonIdx !== -1) {
      const namespace = rawKey.slice(0, colonIdx);
      const subKey = rawKey.slice(colonIdx + 1);

      if (BLOCKED_PROPS.has(namespace) || BLOCKED_PROPS.has(subKey)) continue;

      const existing = ns[namespace];
      if (existing !== undefined && existing !== null && typeof existing !== 'object') {
        continue;
      }

      if (existing === undefined || existing === null) {
        ns[namespace] = Object.create(null);
      }
      ns[namespace][subKey] = val;
    } else {
      if (BLOCKED_PROPS.has(rawKey)) continue;
      ns[rawKey] = val;
    }
  }

  return ns;
}

export function buildContext(data) {
  const ctx = Object.create(null);
  ctx['c'] = extractNamespace(data, 'c:');
  ctx['t'] = extractNamespace(data, 't:');
  ctx['PI'] = Math.PI;
  return ctx;
}

function coerceValue(raw) {
  if (raw === null || raw === undefined) return raw;
  if (typeof raw === 'number' || typeof raw === 'boolean') return raw;
  if (typeof raw !== 'string') return raw;
  if (raw === '') return raw;
  const n = Number(raw);
  return isNaN(n) ? raw : n;
}

// ---------------------------------------------------------------------------
// Built-in functions
// ---------------------------------------------------------------------------

function toComparable(v) {
  if (typeof v === 'number') return v;
  if (v === null || v === undefined || v === '') return -Infinity;
  const s = String(v);
  const n = Number(s);
  if (!isNaN(n)) return n;
  const ms = Date.parse(s);
  return isNaN(ms) ? -Infinity : ms;
}

/**
 * argmax(value1, label1, value2, label2, ...) - returns the label paired with
 * the highest value. argmin / latest / oldest are variants.
 */
function argExtreme(args, mode) {
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

/** Names of arg-family functions that require even argument counts. */
export const ARG_FUNCTIONS = new Set(['argmax', 'argmin', 'latest', 'oldest']);

/** All supported function names. */
export const SUPPORTED_FUNCTIONS = new Set([
  'argmax', 'argmin', 'latest', 'oldest',
  'max', 'min', 'clamp', 'sum', 'avg', 'abs', 'round', 'floor', 'ceil',
  'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2', 'sqrt', 'fract', 'mod',
  'now', 'now_ms',
]);

const FUNCTIONS = {
  argmax: (args) => argExtreme(args, 'max'),
  argmin: (args) => argExtreme(args, 'min'),
  latest: (args) => argExtreme(args, 'max'),
  oldest: (args) => argExtreme(args, 'min'),

  max: (args) => Math.max(...args.map(toNum)),
  min: (args) => Math.min(...args.map(toNum)),
  clamp: (args) => Math.min(Math.max(toNum(args[0]), toNum(args[1])), toNum(args[2])),
  sum: (args) => args.reduce((acc, v) => acc + toNum(v), 0),
  avg: (args) => args.length === 0 ? 0 : args.reduce((acc, v) => acc + toNum(v), 0) / args.length,
  abs: (args) => Math.abs(toNum(args[0])),
  round: (args) => {
    const n = toNum(args[0]);
    if (args.length < 2) return Math.round(n);
    const digits = Math.max(0, Math.min(100, Math.floor(toNum(args[1]))));
    return n.toFixed(digits);
  },
  floor: (args) => Math.floor(toNum(args[0])),
  ceil: (args) => Math.ceil(toNum(args[0])),
  sin: (args) => Math.sin(toNum(args[0])),
  cos: (args) => Math.cos(toNum(args[0])),
  tan: (args) => Math.tan(toNum(args[0])),
  asin: (args) => Math.asin(toNum(args[0])),
  acos: (args) => Math.acos(toNum(args[0])),
  atan: (args) => Math.atan(toNum(args[0])),
  atan2: (args) => Math.atan2(toNum(args[0]), toNum(args[1])),
  sqrt: (args) => {
    const x = toNum(args[0]);
    return x < 0 ? 0 : Math.sqrt(x);
  },
  fract: (args) => {
    const x = toNum(args[0]);
    return x - Math.floor(x);
  },
  mod: (args) => {
    const a = toNum(args[0]);
    const b = toNum(args[1]);
    return b === 0 ? 0 : a - b * Math.floor(a / b);
  },

  now: () => Math.floor(Date.now() / 1000),
  now_ms: () => Date.now(),
};

// ---------------------------------------------------------------------------
// AST evaluator
// ---------------------------------------------------------------------------

export function evaluate(node, ctx) {
  switch (node.type) {
    case 'Literal':
      return node.value;

    case 'Identifier':
      return ctx[node.name];

    case 'MemberExpression': {
      const obj = evaluate(node.object, ctx);
      if (obj === null || obj === undefined || typeof obj !== 'object') return undefined;

      let prop;
      if (node.computed) {
        const propVal = evaluate(node.property, ctx);
        prop = String(propVal ?? '');
      } else {
        prop = node.property.name;
      }

      if (BLOCKED_PROPS.has(prop)) return undefined;
      if (!Object.prototype.hasOwnProperty.call(obj, prop)) return undefined;

      return obj[prop];
    }

    case 'BinaryExpression': {
      const op = BINARY_OPS[node.operator];
      if (!op) return undefined;

      if (node.operator === '&&') {
        const left = evaluate(node.left, ctx);
        return isTruthy(left) ? evaluate(node.right, ctx) : left;
      }
      if (node.operator === '||') {
        const left = evaluate(node.left, ctx);
        return isTruthy(left) ? left : evaluate(node.right, ctx);
      }

      return op(evaluate(node.left, ctx), evaluate(node.right, ctx));
    }

    case 'UnaryExpression': {
      const op = UNARY_OPS[node.operator];
      if (!op) return undefined;
      return op(evaluate(node.argument, ctx));
    }

    case 'ConditionalExpression':
      return isTruthy(evaluate(node.test, ctx))
        ? evaluate(node.consequent, ctx)
        : evaluate(node.alternate, ctx);

    case 'CallExpression': {
      if (node.callee.type !== 'Identifier') return undefined;
      const fn = FUNCTIONS[node.callee.name];
      if (!fn) return undefined;
      const args = node.arguments.map((arg) => evaluate(arg, ctx));
      return fn(args);
    }

    default:
      return undefined;
  }
}

// ---------------------------------------------------------------------------
// AST inspection
// ---------------------------------------------------------------------------

const TIME_FUNCTIONS = new Set(['now', 'now_ms']);

/**
 * Walks the AST looking for a call to any TIME_FUNCTIONS member. Time-
 * dependent expressions need a ticker on the frontend; on the server they
 * just evaluate to "now" at call time.
 */
export function containsNowCall(node) {
  if (!node) return false;
  switch (node.type) {
    case 'CallExpression':
      if (node.callee.type === 'Identifier' && TIME_FUNCTIONS.has(node.callee.name)) {
        return true;
      }
      return node.arguments.some(containsNowCall);
    case 'BinaryExpression':
      return containsNowCall(node.left) || containsNowCall(node.right);
    case 'UnaryExpression':
      return containsNowCall(node.argument);
    case 'ConditionalExpression':
      return containsNowCall(node.test) || containsNowCall(node.consequent) || containsNowCall(node.alternate);
    case 'MemberExpression':
      return containsNowCall(node.object) || (node.computed ? containsNowCall(node.property) : false);
    default:
      return false;
  }
}

// ---------------------------------------------------------------------------
// Result stringification
// ---------------------------------------------------------------------------

export function resultToString(val) {
  if (val === null || val === undefined) return '';
  if (typeof val === 'boolean') return val ? '1' : '0';
  if (typeof val === 'number') {
    if (isNaN(val) || !isFinite(val)) return '0';
    return String(val);
  }
  return String(val);
}

// ---------------------------------------------------------------------------
// One-call convenience: parse + evaluate + stringify
// ---------------------------------------------------------------------------

/**
 * Parse and evaluate an expression in one shot, returning a stringified
 * value. The sidecar uses this; frontend uses the lower-level pieces
 * (parse once, evaluate many times reactively).
 *
 * @param {string} expression - Expression source
 * @param {Object} data - Flat key->value map matching the frontend's data shape (keys like "c:foo", "c:kofi:bar", "t:followers_total")
 * @returns {{ ok: true, value: string } | { ok: false, error: { code: string, message: string } }}
 */
export function evaluateExpression(expression, data) {
  let ast;
  try {
    ast = jsep(expression);
  } catch (e) {
    return {
      ok: false,
      error: { code: 'parse_error', message: String(e?.message ?? e) },
    };
  }

  let value;
  try {
    const ctx = buildContext(data ?? {});
    value = evaluate(ast, ctx);
  } catch (e) {
    return {
      ok: false,
      error: { code: 'eval_error', message: String(e?.message ?? e) },
    };
  }

  return { ok: true, value: resultToString(value) };
}
