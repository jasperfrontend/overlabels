/**
 * Flat `checkins.*` slots for the overlay's checkin iterable.
 *
 * The initial window arrives in the render payload (server-sliced to the
 * user's cap); after that, `checkins.updated` broadcasts carry ONE pin plus
 * the authoritative window count - never the whole window, because 50
 * realistic pins overflow Reverb's 10 KB payload limit. The client owns the
 * window from then on: upsert by login, newest first, trim to the cap. That
 * makes `checkins` the second client-enforced foreach cap after `chat`.
 *
 * Pure module, chatSlots.ts pattern: no Vue, no socket, fully unit-testable.
 * Index 0 = NEWEST (display order - the opposite of chat, where 0 = oldest).
 */

export const CHECKIN_SLOT_PREFIX = 'checkins.';

export const DEFAULT_CHECKINS_WINDOW = 50;

export const PIN_FIELDS = ['name', 'login', 'place', 'country', 'country_code', 'lat', 'lng', 'at', 'distance_km'] as const;

export type CheckinPin = Record<(typeof PIN_FIELDS)[number], string>;

/**
 * The cap as shipped in the render payload. An older server that does not
 * send `checkins_window` yields NaN, which must fall back to the DEFAULT and
 * never to 1 - the chat clamp lesson, where NaN would otherwise pin every
 * overlay to a one-item window.
 */
export function clampCheckinsWindow(value: unknown): number {
  const n = Math.floor(Number(value));
  if (!Number.isFinite(n) || n < 1) return DEFAULT_CHECKINS_WINDOW;
  return Math.min(n, DEFAULT_CHECKINS_WINDOW);
}

/** Normalize a broadcast's pin payload; null when it is not a usable pin. */
export function toPin(raw: unknown): CheckinPin | null {
  if (!raw || typeof raw !== 'object') return null;
  const source = raw as Record<string, unknown>;
  if (typeof source.login !== 'string' || source.login === '') return null;

  const pin = {} as CheckinPin;
  for (const field of PIN_FIELDS) {
    const value = source[field];
    pin[field] = typeof value === 'string' ? value : value == null ? '' : String(value);
  }
  return pin;
}

/** Read the current window back out of the flat data object. */
export function pinsFromData(data: Record<string, unknown>): CheckinPin[] {
  const pins: CheckinPin[] = [];
  for (let i = 0; ; i++) {
    if (typeof data[`${CHECKIN_SLOT_PREFIX}${i}.login`] !== 'string') break;
    const pin = {} as CheckinPin;
    for (const field of PIN_FIELDS) {
      const value = data[`${CHECKIN_SLOT_PREFIX}${i}.${field}`];
      pin[field] = typeof value === 'string' ? value : '';
    }
    pins.push(pin);
  }
  return pins;
}

/**
 * Latest wins: the pin's previous position (matched by login) disappears and
 * it re-enters at index 0, trimmed to the cap.
 */
export function upsertPin(pins: CheckinPin[], pin: CheckinPin, cap: number): CheckinPin[] {
  const rest = pins.filter((p) => p.login !== pin.login);
  return [pin, ...rest].slice(0, Math.max(1, cap));
}

/**
 * Drop every previous `checkins.*` key, then write the given window and the
 * authoritative count. The drop-then-write is what stops a shrunk or cleared
 * window from resurrecting stale pins (the withChatSlots rule).
 */
export function withCheckinSlots(data: Record<string, unknown>, pins: CheckinPin[], count: number): Record<string, unknown> {
  const next: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(data)) {
    if (!key.startsWith(CHECKIN_SLOT_PREFIX)) {
      next[key] = value;
    }
  }

  next[`${CHECKIN_SLOT_PREFIX}count`] = String(Math.max(0, count));
  pins.forEach((pin, i) => {
    for (const field of PIN_FIELDS) {
      next[`${CHECKIN_SLOT_PREFIX}${i}.${field}`] = pin[field];
    }
  });

  return next;
}
