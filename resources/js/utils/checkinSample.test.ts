import { describe, expect, it } from 'vitest';
import { SAMPLE_CHECKINS_MAX, sampleCheckinPins } from './checkinSample';
import { PIN_FIELDS, toPin } from './checkinSlots';

describe('sampleCheckinPins', () => {
  it('returns as many pins as asked, clamped to the list', () => {
    expect(sampleCheckinPins(0, 1000)).toEqual([]);
    expect(sampleCheckinPins(-3, 1000)).toEqual([]);
    expect(sampleCheckinPins(12, 1000)).toHaveLength(12);
    expect(sampleCheckinPins(SAMPLE_CHECKINS_MAX + 20, 1000)).toHaveLength(SAMPLE_CHECKINS_MAX);
  });

  it('keeps the smaller set when asked for more, so the slider never reshuffles the globe', () => {
    const few = sampleCheckinPins(5, 1000).map((p) => p.login);
    const many = sampleCheckinPins(20, 1000).map((p) => p.login);
    expect(many).toEqual(expect.arrayContaining(few));
  });

  it('is newest first, with every pin the overlay accepts', () => {
    const pins = sampleCheckinPins(10, 1000);
    const at = pins.map((p) => Number(p.at));
    expect(at).toEqual([...at].sort((a, b) => b - a));
    expect(at[0]).toBe(1000);

    for (const pin of pins) {
      expect(toPin(pin)).toEqual(pin);
      expect(Object.keys(pin).sort()).toEqual([...PIN_FIELDS].sort());
      expect(Number.isFinite(Number(pin.lat)) && Math.abs(Number(pin.lat)) <= 90).toBe(true);
      expect(Number.isFinite(Number(pin.lng)) && Math.abs(Number(pin.lng)) <= 180).toBe(true);
    }
  });

  it('gives every viewer their own login', () => {
    const logins = sampleCheckinPins(SAMPLE_CHECKINS_MAX, 1000).map((p) => p.login);
    expect(new Set(logins).size).toBe(logins.length);
  });
});
