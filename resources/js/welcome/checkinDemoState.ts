/**
 * Pure state for the homepage Chat Checkin demo: the parsing, the HUD math
 * and the pin shape, with no DOM and no three.js so vitest can reach it.
 *
 * The demo mirrors the product's rules rather than inventing its own: a
 * re-checkin moves the viewer's pin (latest wins, upsert by login), the count
 * is the number of pins, countries are the distinct country codes among
 * them, and the three HUD lines read exactly as the product's globe recipe
 * words them (resources/recipes/chat-checkin/globe.md).
 *
 * "Home" for the farthest-from-home line is the Overlabels mark at Avarua,
 * Cook Islands - the brand pin every globe carries (see globe/brandPin.ts).
 * The product measures from the streamer's own home setting instead; the
 * homepage has no streamer, so the maker's home stands in.
 */

import type { CheckinPin } from '@/utils/checkinSlots';

export const HOME = { lat: -21.2075, lng: -159.77546 } as const;

export interface DemoPlace {
  name: string;
  country_code: string;
  country: string;
  label: string;
  lat: number;
  lng: number;
}

export interface DemoCheckin {
  name: string;
  login: string;
  place: DemoPlace;
  /** Unix seconds, the `_at` contract. */
  at: number;
}

export interface DemoState {
  /** Arrival order, one entry per login; the newest is last. */
  checkins: DemoCheckin[];
  /** The last successful checkin, whether or not it moved an existing pin. */
  latest: DemoCheckin | null;
}

export const EMPTY_STATE: DemoState = { checkins: [], latest: null };

export type ParsedCommand = { place: string } | { empty: true };

/**
 * What the visitor typed, read the way the bot reads chat: "!checkin Avarua",
 * "checkin Avarua" and a bare "Avarua" all mean Avarua. A bare "!checkin"
 * (or nothing) is the empty case the bot answers with its "Where are you?"
 * line.
 */
export function parseCommand(input: string): ParsedCommand {
  const place = input
    .trim()
    .replace(/^!?checkin(?=\s|$)/i, '')
    .trim();

  return place === '' ? { empty: true } : { place };
}

const EARTH_RADIUS_KM = 6371;

/** Great-circle distance in kilometers - the same formula as GeoMath::haversineDistance. */
export function haversineKm(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
  const rad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat);
  const dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;

  return 2 * EARTH_RADIUS_KM * Math.asin(Math.sqrt(h));
}

/** Latest wins: the same login's earlier pin disappears and the new one goes last. */
export function nextState(state: DemoState, checkin: DemoCheckin): DemoState {
  const rest = state.checkins.filter((c) => c.login !== checkin.login);

  return { checkins: [...rest, checkin], latest: checkin };
}

export const MILE_IN_KM = 1.609344;

/** The product's |distance: pipe (formatters.ts formatDistance): up to two decimals, thousands separated. */
const distanceFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

/** The km the bot speaks (BotCheckinController): one decimal, trailing .0 dropped, thousands separated. */
const spokenKmFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 });

export function distanceFromHomeKm(checkin: DemoCheckin): number {
  return haversineKm(HOME, checkin.place);
}

/** The pin that has travelled farthest from home, or null when no pin is at least 1 km out. */
export function farthest(state: DemoState): DemoCheckin | null {
  let best: DemoCheckin | null = null;
  let bestKm = 0;

  for (const checkin of state.checkins) {
    const km = distanceFromHomeKm(checkin);
    if (km > bestKm) {
      best = checkin;
      bestKm = km;
    }
  }

  return bestKm >= 1 ? best : null;
}

export interface HudLines {
  count: string;
  latest: string | null;
  farthest: string | null;
}

/**
 * The three HUD lines the product overlay renders with the HUD toggle on,
 * plural rules included: "1 checkin from 1 country", "2 checkins from 2
 * countries". The latest and farthest lines are null until there is
 * something to say, like the product's [[[if]]] around them.
 */
export function hudLines(state: DemoState): HudLines {
  const count = state.checkins.length;
  const countries = new Set(state.checkins.map((c) => c.place.country_code)).size;

  const far = farthest(state);
  let farthestLine: string | null = null;
  if (far) {
    // The product control holds km rounded to one decimal, and the recipe
    // renders it through |distance:km and |distance:mi, so 16,606.4km
    // (10,318.36mi) is what a real overlay shows.
    const km = Math.round(distanceFromHomeKm(far) * 10) / 10;
    farthestLine = `farthest from home: ${distanceFormat.format(km)}km (${distanceFormat.format(km / MILE_IN_KM)}mi) by ${far.name}`;
  }

  return {
    count: `${count} checkin${count === 1 ? '' : 's'} from ${countries} countr${countries === 1 ? 'y' : 'ies'}`,
    latest: state.latest ? `${state.latest.name} checked in last from ${state.latest.place.label}` : null,
    farthest: farthestLine,
  };
}

/** The pin the globe draws for a checkin: the checkinSlots shape, every field a string. */
export function toPin(checkin: DemoCheckin): CheckinPin {
  return {
    name: checkin.name,
    login: checkin.login,
    place: checkin.place.label,
    country: checkin.place.country,
    country_code: checkin.place.country_code,
    lat: String(checkin.place.lat),
    lng: String(checkin.place.lng),
    at: String(checkin.at),
    distance: String(Math.round(distanceFromHomeKm(checkin) * 10) / 10),
  };
}

export function pins(state: DemoState): CheckinPin[] {
  return state.checkins.map(toPin);
}

/* ---- the bot's lines, word for word from BotCheckinController ---- */

export const REPLY_EMPTY = 'Where are you? Try !checkin City, CC - like !checkin Rotterdam, NL';
export const REPLY_MISS = "Couldn't find that place. Try City, CC - like Rotterdam, NL";

/**
 * "{name} checked in from {label}!" and, with a home set and the pin at least
 * a kilometre out (after the controller's one-decimal rounding), " That is
 * {km} km away." The demo is a home-set world, so the tail is part of it.
 */
export function successReply(name: string, label: string, km?: number): string {
  const reply = `${name} checked in from ${label}!`;
  if (km === undefined) return reply;

  const rounded = Math.round(km * 10) / 10;
  if (rounded < 1) return reply;

  return `${reply} That is ${spokenKmFormat.format(rounded)} km away.`;
}

/* ---- the endpoint's answer, narrowed ---- */

/** Read the resolve endpoint's JSON into a place, or null when it is not one. */
export function placeFromResponse(raw: unknown): DemoPlace | null {
  if (!raw || typeof raw !== 'object') return null;
  const place = (raw as { place?: unknown }).place;
  if (!place || typeof place !== 'object') return null;

  const p = place as Record<string, unknown>;
  const lat = Number(p.lat);
  const lng = Number(p.lng);
  if (typeof p.name !== 'string' || typeof p.country_code !== 'string' || !Number.isFinite(lat) || !Number.isFinite(lng)) {
    return null;
  }

  return {
    name: p.name,
    country_code: p.country_code,
    country: typeof p.country === 'string' ? p.country : '',
    label: typeof p.label === 'string' && p.label !== '' ? p.label : `${p.name}, ${p.country_code}`,
    lat,
    lng,
  };
}

/** The bot's reply on a miss or a refusal, falling back to the miss line when the body has none. */
export function replyFromResponse(raw: unknown): string {
  if (raw && typeof raw === 'object') {
    const reply = (raw as { reply?: unknown }).reply;
    if (typeof reply === 'string' && reply !== '') return reply;
  }

  return REPLY_MISS;
}
