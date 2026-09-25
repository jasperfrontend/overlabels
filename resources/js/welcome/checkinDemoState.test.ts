import { describe, expect, it } from 'vitest';
import {
  EMPTY_STATE,
  HOME,
  REPLY_MISS,
  farthest,
  haversineKm,
  hudLines,
  nextState,
  parseCommand,
  pins,
  placeFromResponse,
  replyFromResponse,
  successReply,
  toPin,
  type DemoCheckin,
  type DemoPlace,
} from './checkinDemoState';

const OSAKA: DemoPlace = { name: 'Osaka', country_code: 'JP', country: 'Japan', label: 'Osaka, JP', lat: 34.6937, lng: 135.5023 };
const LISBON: DemoPlace = { name: 'Lisbon', country_code: 'PT', country: 'Portugal', label: 'Lisbon, PT', lat: 38.7167, lng: -9.1333 };
const PORTO: DemoPlace = { name: 'Porto', country_code: 'PT', country: 'Portugal', label: 'Porto, PT', lat: 41.1496, lng: -8.611 };
const AVARUA: DemoPlace = { name: 'Avarua', country_code: 'CK', country: 'Cook Islands', label: 'Avarua, CK', lat: HOME.lat, lng: HOME.lng };

const checkin = (name: string, place: DemoPlace, at = 1_700_000_000): DemoCheckin => ({ name, login: name.toLowerCase(), place, at });

describe('parseCommand', () => {
  it('reads the place after !checkin', () => {
    expect(parseCommand('!checkin Avarua')).toEqual({ place: 'Avarua' });
  });

  it('accepts the command without the bang, or the bare place', () => {
    expect(parseCommand('checkin Rotterdam, NL')).toEqual({ place: 'Rotterdam, NL' });
    expect(parseCommand('Rotterdam, NL')).toEqual({ place: 'Rotterdam, NL' });
  });

  it('is case-insensitive about the verb and trims whitespace', () => {
    expect(parseCommand('  !CheckIn   Cape Town  ')).toEqual({ place: 'Cape Town' });
  });

  it('treats a bare !checkin, or nothing at all, as empty', () => {
    expect(parseCommand('!checkin')).toEqual({ empty: true });
    expect(parseCommand('!checkin   ')).toEqual({ empty: true });
    expect(parseCommand('   ')).toEqual({ empty: true });
  });

  it('does not eat a place that merely starts with the verb', () => {
    expect(parseCommand('checkinville')).toEqual({ place: 'checkinville' });
  });
});

describe('haversineKm', () => {
  it('puts Osaka roughly nine to ten thousand kilometers from Avarua', () => {
    const km = haversineKm(HOME, OSAKA);
    expect(km).toBeGreaterThan(9000);
    expect(km).toBeLessThan(10000);
  });

  it('is zero for the same point and symmetric', () => {
    expect(haversineKm(HOME, HOME)).toBe(0);
    expect(haversineKm(HOME, LISBON)).toBeCloseTo(haversineKm(LISBON, HOME), 6);
  });
});

describe('nextState', () => {
  it('appends a new login and records it as latest', () => {
    const state = nextState(EMPTY_STATE, checkin('sunroof', OSAKA));
    expect(state.checkins).toHaveLength(1);
    expect(state.latest?.name).toBe('sunroof');
  });

  it('moves an existing pin instead of adding a second one (latest wins)', () => {
    let state = nextState(EMPTY_STATE, checkin('you', OSAKA));
    state = nextState(state, checkin('pixelmoth', LISBON));
    state = nextState(state, checkin('you', PORTO));

    expect(state.checkins.map((c) => c.place.name)).toEqual(['Lisbon', 'Porto']);
    expect(state.latest?.place.name).toBe('Porto');
  });

  it('does not mutate the previous state', () => {
    const before = nextState(EMPTY_STATE, checkin('you', OSAKA));
    nextState(before, checkin('you', PORTO));
    expect(before.checkins[0].place.name).toBe('Osaka');
  });
});

describe('hudLines', () => {
  it('reads "0 checkins from 0 countries" with nothing to say for latest and farthest', () => {
    expect(hudLines(EMPTY_STATE)).toEqual({ count: '0 checkins from 0 countries', latest: null, farthest: null });
  });

  it('uses the singular for one checkin from one country', () => {
    const lines = hudLines(nextState(EMPTY_STATE, checkin('sunroof', OSAKA)));
    expect(lines.count).toBe('1 checkin from 1 country');
    expect(lines.latest).toBe('sunroof checked in last from Osaka, JP');
  });

  it('uses the plural for two checkins from two countries', () => {
    let state = nextState(EMPTY_STATE, checkin('sunroof', OSAKA));
    state = nextState(state, checkin('pixelmoth', LISBON));
    expect(hudLines(state).count).toBe('2 checkins from 2 countries');
  });

  it('counts countries by distinct country code, not by pin', () => {
    let state = nextState(EMPTY_STATE, checkin('pixelmoth', LISBON));
    state = nextState(state, checkin('kettle_', PORTO));
    expect(hudLines(state).count).toBe('2 checkins from 1 country');
  });

  it('names the farthest pin from Avarua the way |distance:km and |distance:mi render it', () => {
    let state = nextState(EMPTY_STATE, checkin('sunroof', OSAKA));
    state = nextState(state, checkin('pixelmoth', LISBON));

    const line = hudLines(state).farthest;
    // Up to two decimals, thousands separated, no trailing zeros - formatDistance's output.
    expect(line).toMatch(/^farthest from home: \d{1,3}(,\d{3})*(\.\d{1,2})?km \(\d{1,3}(,\d{3})*(\.\d{1,2})?mi\) by pixelmoth$/);

    const [, km, mi] = line!.match(/([\d,.]+)km \(([\d,.]+)mi\)/)!;
    const kmN = Number(km.replace(/,/g, ''));
    const miN = Number(mi.replace(/,/g, ''));
    expect(kmN).toBeGreaterThan(16000);
    expect(kmN).toBeLessThan(17000);
    // The km is the one-decimal stored value; the miles come from it.
    expect(kmN).toBe(Math.round(haversineKm(HOME, LISBON) * 10) / 10);
    expect(miN).toBeCloseTo(kmN / 1.609344, 2);
  });

  it('leaves the farthest line out while every pin sits at home', () => {
    const state = nextState(EMPTY_STATE, checkin('you', AVARUA));
    expect(farthest(state)).toBeNull();
    expect(hudLines(state).farthest).toBeNull();
  });
});

describe('toPin', () => {
  it('produces the checkinSlots shape with every field a string', () => {
    const pin = toPin(checkin('sunroof', OSAKA, 1_700_000_123));
    expect(pin).toEqual({
      name: 'sunroof',
      login: 'sunroof',
      place: 'Osaka, JP',
      country: 'Japan',
      country_code: 'JP',
      lat: '34.6937',
      lng: '135.5023',
      at: '1700000123',
      distance: expect.stringMatching(/^\d+(\.\d)?$/),
    });
    expect(pins(nextState(EMPTY_STATE, checkin('sunroof', OSAKA)))).toHaveLength(1);
  });
});

describe('the bot lines', () => {
  it('phrases success like BotCheckinController', () => {
    expect(successReply('you', 'Avarua, CK')).toBe('you checked in from Avarua, CK!');
  });

  it('appends the distance tail the bot speaks when a home is set', () => {
    // Whole km: no trailing .0, thousands separated.
    expect(successReply('pixelmoth', 'Lisbon, PT', 16606)).toBe('pixelmoth checked in from Lisbon, PT! That is 16,606 km away.');
    // One decimal kept, and the raw value rounded to it first.
    expect(successReply('you', 'Arorangi, CK', 57.46)).toBe('you checked in from Arorangi, CK! That is 57.5 km away.');
    expect(successReply('you', 'Somewhere, XX', 12345.7)).toBe('you checked in from Somewhere, XX! That is 12,345.7 km away.');
    // Under a kilometre after rounding, the bot says nothing about distance.
    expect(successReply('you', 'Avarua, CK', 0.4)).toBe('you checked in from Avarua, CK!');
    expect(successReply('you', 'Avarua, CK', 0.96)).toBe('you checked in from Avarua, CK! That is 1 km away.');
  });

  it('reads the endpoint body and falls back to the miss line', () => {
    expect(
      placeFromResponse({
        place: { name: 'Avarua', country_code: 'CK', country: 'Cook Islands', label: 'Avarua, CK', lat: -21.2075, lng: -159.77546 },
      }),
    ).toEqual(AVARUA);
    expect(placeFromResponse({ place: null, reply: 'nope' })).toBeNull();
    expect(placeFromResponse({ place: { name: 'x', country_code: 'XX', lat: 'nan', lng: 0 } })).toBeNull();
    expect(replyFromResponse({ place: null, reply: 'Try City, CC' })).toBe('Try City, CC');
    expect(replyFromResponse('not json')).toBe(REPLY_MISS);
  });
});
