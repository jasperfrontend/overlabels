/**
 * Pipe-based formatting system for template tags.
 *
 * Syntax: [[[tag_name|formatter]]] or [[[tag_name|formatter:args]]]
 *
 * Built-in formatters:
 *   |round        — no decimals
 *   |round:N      — N decimal places
 *   |duration     — smart human-readable (auto-selects units)
 *   |duration:FMT — explicit pattern (hh:mm:ss, mm:ss, dd:hh:mm:ss, etc.)
 *   |currency     — locale-aware currency (uses global locale + default currency)
 *   |currency:EUR — explicit currency code
 *   |date         — locale-aware date
 *   |date:FMT     — explicit date format (dd-MM-yyyy, etc.)
 *   |number       — locale-aware number with thousands separators
 *   |number:N     — locale-aware number with N decimal places
 *   |uppercase    — text transform
 *   |lowercase    — text transform
 */

const DEFAULT_LOCALE = 'en-US';

/**
 * Map supported locales to their typical currency.
 * Used when |currency pipe has no explicit currency code argument.
 */
const LOCALE_CURRENCY_MAP: Record<string, string> = {
  'en-US': 'USD',
  'en-GB': 'GBP',
  'nl-NL': 'EUR',
  'nl-BE': 'EUR',
  'de-DE': 'EUR',
  'fr-FR': 'EUR',
  'es-ES': 'EUR',
  'pt-BR': 'BRL',
  'ja-JP': 'JPY',
  'ko-KR': 'KRW',
};

export function getDefaultCurrency(locale: string): string {
  return LOCALE_CURRENCY_MAP[locale] || 'USD';
}

/**
 * Parse a pipe expression into formatter name and arguments.
 * e.g. "duration:hh:mm:ss" → { name: "duration", args: "hh:mm:ss" }
 * e.g. "round:2" → { name: "round", args: "2" }
 * e.g. "uppercase" → { name: "uppercase", args: undefined }
 */
export function parsePipe(pipe: string): { name: string; args?: string } {
  const colonIndex = pipe.indexOf(':');
  if (colonIndex === -1) {
    return { name: pipe };
  }
  return {
    name: pipe.substring(0, colonIndex),
    args: pipe.substring(colonIndex + 1),
  };
}

/**
 * Apply a pipe formatter to a raw value.
 */
export function applyFormatter(rawValue: string, pipe: string, locale?: string): string {
  const { name, args } = parsePipe(pipe);
  const loc = locale || DEFAULT_LOCALE;

  switch (name) {
    case 'round':
      return formatRound(rawValue, args);
    case 'duration':
      return formatDuration(rawValue, args);
    case 'currency':
      return formatCurrency(rawValue, args, loc);
    case 'date':
      return formatDate(rawValue, args, loc);
    case 'number':
      return formatNumber(rawValue, args, loc);
    case 'uppercase':
      return rawValue.toUpperCase();
    case 'lowercase':
      return rawValue.toLowerCase();
    default:
      // Unknown formatter — return value unchanged
      return rawValue;
  }
}

// --- Formatter implementations ---

function formatRound(value: string, args?: string): string {
  const num = Number(value);
  if (isNaN(num)) return value;
  const decimals = args ? parseInt(args, 10) : 0;
  if (isNaN(decimals) || decimals < 0) return value;
  return num.toFixed(decimals);
}

/**
 * Format seconds into a duration string.
 *
 * Without args: smart auto-format based on magnitude.
 * With args: pattern string using dd, hh, mm, ss tokens.
 *   e.g. "hh:mm:ss" → "02:15:07"
 *   e.g. "mm:ss"    → "135:07"
 *   e.g. "dd:hh:mm:ss" → "01:02:15:07"
 */
function formatDuration(value: string, args?: string): string {
  const totalSeconds = Math.floor(Number(value));
  if (isNaN(totalSeconds)) return value;

  const absSeconds = Math.abs(totalSeconds);
  const sign = totalSeconds < 0 ? '-' : '';

  if (!args) {
    return sign + formatDurationAuto(absSeconds);
  }

  return sign + formatDurationPattern(absSeconds, args);
}

function formatDurationAuto(totalSeconds: number): string {
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (days > 0) {
    const parts: string[] = [];
    parts.push(`${days}d`);
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0) parts.push(`${minutes}m`);
    if (parts.length === 1 && seconds > 0) parts.push(`${seconds}s`);
    return parts.join(' ');
  }

  if (hours > 0) {
    return `${hours}:${pad(minutes)}:${pad(seconds)}`;
  }

  return `${minutes}:${pad(seconds)}`;
}

function formatDurationPattern(totalSeconds: number, pattern: string): string {
  // Determine which units the pattern uses
  const hasDD = pattern.includes('dd');
  const hasHH = pattern.includes('hh');
  const hasMM = pattern.includes('mm');
  const hasSS = pattern.includes('ss');

  let remaining = totalSeconds;
  let days = 0;
  let hours = 0;
  let minutes = 0;
  let seconds = 0;

  // Decompose from largest present unit down; overflow into the largest unit
  if (hasDD) {
    days = Math.floor(remaining / 86400);
    remaining %= 86400;
  }
  if (hasHH) {
    hours = Math.floor(remaining / 3600);
    remaining %= 3600;
  }
  if (hasMM) {
    minutes = Math.floor(remaining / 60);
    remaining %= 60;
  }
  if (hasSS) {
    seconds = remaining;
  }

  // If a unit is absent, its time overflows into the next smaller unit
  // e.g. pattern "mm:ss" with 3661s → mm=61, ss=01
  let result = pattern;
  if (hasDD) result = result.replace('dd', pad(days));
  if (hasHH) result = result.replace('hh', pad(hours));
  if (hasMM) result = result.replace('mm', pad(minutes));
  if (hasSS) result = result.replace('ss', pad(seconds));

  return result;
}

function pad(n: number): string {
  return n < 10 ? `0${n}` : String(n);
}

function formatCurrency(value: string, args?: string, locale?: string): string {
  const num = Number(value);
  if (isNaN(num)) return value;
  const currency = args || getDefaultCurrency(locale || DEFAULT_LOCALE);
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
    }).format(num);
  } catch {
    // Invalid currency code — fall back to plain number
    return num.toFixed(2);
  }
}

/**
 * Format a date/datetime string.
 *
 * Without args: locale-aware default (e.g. "Apr 5, 2026")
 * With args: simple format pattern using dd, MM, yyyy, HH, mm, ss tokens.
 */
function formatDate(value: string, args?: string, locale?: string): string {
  const date = new Date(value);
  if (isNaN(date.getTime())) return value;

  if (!args) {
    return new Intl.DateTimeFormat(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(date);
  }

  // Simple token replacement for explicit patterns
  let result = args;
  result = result.replace('yyyy', String(date.getFullYear()));
  result = result.replace('MM', pad(date.getMonth() + 1));
  result = result.replace('dd', pad(date.getDate()));
  result = result.replace('HH', pad(date.getHours()));
  result = result.replace('mm', pad(date.getMinutes()));
  result = result.replace('ss', pad(date.getSeconds()));

  return result;
}

function formatNumber(value: string, args?: string, locale?: string): string {
  const num = Number(value);
  if (isNaN(num)) return value;

  const options: Intl.NumberFormatOptions = {};
  if (args !== undefined) {
    const decimals = parseInt(args, 10);
    if (!isNaN(decimals) && decimals >= 0) {
      options.minimumFractionDigits = decimals;
      options.maximumFractionDigits = decimals;
    }
  }

  return new Intl.NumberFormat(locale, options).format(num);
}
