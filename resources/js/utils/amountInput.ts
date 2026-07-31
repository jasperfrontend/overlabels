/**
 * Parsing for money amounts that people type by hand.
 *
 * A streamer in Rotterdam types "65,35" and a streamer in Denver types "65.35"
 * for the same amount, and both are right. The server wants exactly one shape,
 * so every separator question is settled here, in the browser, before the value
 * is sent.
 *
 * This module only goes text -> number. To render a number back to the user,
 * use `toLocaleString` / `Intl.NumberFormat` as the rest of the app does.
 */

/** The decimal separator this locale writes: "." for en-US, "," for nl-NL. */
function localeDecimalSeparator(locale?: string): string {
  try {
    return new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === 'decimal')?.value ?? '.';
  } catch {
    return '.';
  }
}

/**
 * Parse a hand-typed amount into a plain number, rounded to 2 decimals.
 *
 * Accepts either separator in either role, plus stray currency symbols and
 * spaces. Which separator means "decimal point" is decided in this order:
 *
 *   1. Both separators present: the last one is the decimal point.
 *      "1.234,56" -> 1234.56 and "1,234.56" -> 1234.56
 *   2. One separator, used more than once: grouping, so no decimals.
 *      "1.234.567" -> 1234567
 *   3. One separator whose trailing digits are not a group of three: decimal.
 *      "65,35" -> 65.35 and "0.5" -> 0.5
 *   4. One separator with exactly three digits after it is genuinely ambiguous
 *      ("1,234" is 1234 in Denver and 1.234 in Rotterdam), so the user's own
 *      locale breaks the tie.
 *
 * Returns null when the string holds no number at all, which callers use to
 * keep the submit button disabled.
 */
export function parseAmountInput(raw: string, locale?: string): number | null {
  if (typeof raw !== 'string') return null;

  // Drop anything that cannot be part of a number: currency symbols, ordinary
  // spaces, and the non-breaking spaces some locales group thousands with.
  const cleaned = raw.replace(/[^\d.,-]/g, '');
  if (!/\d/.test(cleaned)) return null;

  const negative = cleaned.startsWith('-');
  const body = cleaned.replace(/-/g, '');

  const lastDot = body.lastIndexOf('.');
  const lastComma = body.lastIndexOf(',');

  let decimalSep: string | null = null;

  if (lastDot !== -1 && lastComma !== -1) {
    decimalSep = lastDot > lastComma ? '.' : ',';
  } else if (lastDot !== -1 || lastComma !== -1) {
    const sep = lastDot !== -1 ? '.' : ',';
    const used = body.split(sep).length - 1;
    const trailing = body.length - body.lastIndexOf(sep) - 1;

    if (used === 1 && (trailing !== 3 || localeDecimalSeparator(locale) === sep)) {
      decimalSep = sep;
    }
  }

  const normalized =
    decimalSep === null
      ? body.replace(/[.,]/g, '')
      : body
          .split(decimalSep === '.' ? ',' : '.')
          .join('')
          .replace(decimalSep, '.');

  const value = Number(normalized);
  if (!Number.isFinite(value)) return null;

  return Number((negative ? -value : value).toFixed(2));
}
