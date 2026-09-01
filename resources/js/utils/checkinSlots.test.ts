import { describe, expect, it } from 'vitest';
import { clampCheckinsWindow, DEFAULT_CHECKINS_WINDOW, pinsFromData, toPin, upsertPin, withCheckinSlots, type CheckinPin } from './checkinSlots';

function pin(login: string, place = 'Rotterdam, NL'): CheckinPin {
  return {
    name: login.toUpperCase(),
    login,
    place,
    country: 'Netherlands',
    country_code: 'NL',
    lat: '51.9225',
    lng: '4.47917',
    at: '1756700000',
    distance: '',
  };
}

describe('clampCheckinsWindow', () => {
  it('passes a sane value through', () => {
    expect(clampCheckinsWindow(25)).toBe(25);
    expect(clampCheckinsWindow('10')).toBe(10);
  });

  it('falls back to the DEFAULT on NaN, never to 1', () => {
    // An older server does not ship checkins_window; Number(undefined) is NaN.
    expect(clampCheckinsWindow(undefined)).toBe(DEFAULT_CHECKINS_WINDOW);
    expect(clampCheckinsWindow('nope')).toBe(DEFAULT_CHECKINS_WINDOW);
  });

  it('clamps to the ceiling and floor', () => {
    expect(clampCheckinsWindow(9999)).toBe(DEFAULT_CHECKINS_WINDOW);
    expect(clampCheckinsWindow(0)).toBe(DEFAULT_CHECKINS_WINDOW);
    expect(clampCheckinsWindow(-3)).toBe(DEFAULT_CHECKINS_WINDOW);
  });
});

describe('toPin', () => {
  it('normalizes a broadcast pin and stringifies stray values', () => {
    const result = toPin({ ...pin('viewer'), at: 1756700000 as unknown as string });
    expect(result?.at).toBe('1756700000');
  });

  it('rejects payloads without a login', () => {
    expect(toPin(null)).toBeNull();
    expect(toPin({})).toBeNull();
    expect(toPin({ place: 'Rotterdam, NL' })).toBeNull();
  });
});

describe('upsertPin', () => {
  it('puts a new pin at index 0', () => {
    const pins = upsertPin([pin('a'), pin('b')], pin('c'), 50);
    expect(pins.map((p) => p.login)).toEqual(['c', 'a', 'b']);
  });

  it('moves an existing pin instead of duplicating it - latest wins', () => {
    const pins = upsertPin([pin('a'), pin('b')], pin('b', 'Paris, FR'), 50);
    expect(pins.map((p) => p.login)).toEqual(['b', 'a']);
    expect(pins[0].place).toBe('Paris, FR');
  });

  it('trims to the cap, dropping the oldest', () => {
    const pins = upsertPin([pin('a'), pin('b'), pin('c')], pin('d'), 3);
    expect(pins.map((p) => p.login)).toEqual(['d', 'a', 'b']);
  });
});

describe('withCheckinSlots and pinsFromData', () => {
  it('round-trips a window through the flat data shape', () => {
    const data = withCheckinSlots({ other: 'kept' }, [pin('a'), pin('b')], 7);

    expect(data.other).toBe('kept');
    expect(data['checkins.count']).toBe('7');
    expect(data['checkins.0.login']).toBe('a');
    expect(data['checkins.1.login']).toBe('b');
    expect(pinsFromData(data).map((p) => p.login)).toEqual(['a', 'b']);
  });

  it('drops every stale slot before writing - a cleared window cannot resurrect pins', () => {
    const before = withCheckinSlots({}, [pin('a'), pin('b'), pin('c')], 3);
    const after = withCheckinSlots(before, [], 0);

    expect(after['checkins.count']).toBe('0');
    expect(after['checkins.0.login']).toBeUndefined();
    expect(after['checkins.2.place']).toBeUndefined();
    expect(pinsFromData(after)).toEqual([]);
  });

  it('a shrunk window leaves no orphaned high indexes', () => {
    const before = withCheckinSlots({}, [pin('a'), pin('b'), pin('c')], 3);
    const after = withCheckinSlots(before, [pin('d')], 1);

    expect(after['checkins.0.login']).toBe('d');
    expect(after['checkins.1.login']).toBeUndefined();
  });
});
