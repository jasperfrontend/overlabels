/**
 * TypeScript reader for the shared DSL spec at resources/dsl/dsl.json.
 *
 * Counterpart to app/Support/Dsl.php. Both build their patterns from the same
 * file, so the vocabulary and the lexical shape cannot drift apart. Before this
 * existed, five hand-maintained regexes matched tags independently and had
 * diverged in seven separate ways (D1-D7 in docs/design/overlabels-dsl-spec.md).
 *
 * NOTHING may hand-roll a tag regex. Add new shapes here so both runtimes get
 * them at once.
 */

import spec from '../../dsl/dsl.json';

export const DSL = spec;

const lex = spec.lexical;

/**
 * Build the canonical tag pattern.
 *
 * Capture groups:
 *   1 - tag key
 *   2 - pipe expression (formatter plus optional :args), optional
 *   3 - `?? default` literal, optional
 *
 * Returns a fresh RegExp each call: a `g`-flagged regex carries mutable
 * lastIndex, so sharing one instance across call sites is a footgun.
 */
export function tagPattern(flags = 'g'): RegExp {
  return new RegExp(
    lex.open +
      `([${lex.keyStart}][${lex.keyRest}]*)` +
      // A pipe may contain spaces but must not END on one, or the greedy class
      // eats the space before `??` and the formatter gets a trailing space (D8).
      `(?:${lex.pipeOperator}([${lex.pipeArgs}]*[${lex.pipeArgsEnd}]))?` +
      `\\s*` +
      `(?:${lex.defaultOperator}\\s*(.*?))?` +
      lex.close,
    flags,
  );
}

/**
 * Build the block-token pattern for if / elseif / else / endif / foreach /
 * endforeach.
 *
 * Capture groups:
 *   1 - whole token body
 *   2 - `if` condition
 *   3 - `elseif` condition
 *   4 - `foreach` expression (`iterable as alias`)
 *
 * Alternation order matters: `elseif:` must precede `else` or `[[[elseif:x]]]`
 * lexes as `else` followed by garbage.
 */
export function blockTokenPattern(flags = 'g'): RegExp {
  const body = lex.blockBody;

  return new RegExp(
    lex.open +
      `(if:(${body})|elseif:(${body})|else|endif|foreach:(${body})|endforeach)` +
      lex.close,
    flags,
  );
}

/**
 * Matches a full condition: key, then an optional operator and value.
 * Operators are alternated longest-first so `>=` wins over `>`.
 */
export function conditionPattern(): RegExp {
  const ops = spec.comparisonOperators
    .map((op) => op.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
    .join('|');

  return new RegExp(
    `^([${lex.keyStart}][${lex.keyRest}]*)\\s*(${ops})\\s*(.+)$`,
  );
}

export type FormatterName = keyof typeof spec.formatters;

export const FORMATTER_NAMES = Object.keys(spec.formatters) as FormatterName[];

export function isFormatter(name: string): name is FormatterName {
  return Object.prototype.hasOwnProperty.call(spec.formatters, name);
}

export const MAX_NESTING_DEPTH = spec.limits.maxNestingDepth;
export const FOREACH_CAP_MAX = spec.limits.foreachCapMax;

/** Split a tag key into its colon-delimited segments. */
export function segments(key: string): string[] {
  return key.split(':');
}
