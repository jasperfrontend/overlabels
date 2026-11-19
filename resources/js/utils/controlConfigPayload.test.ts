import { describe, expect, it } from 'vitest';
import { controlConfigPayload } from './controlConfigPayload';

const look = {
  choices: [
    { value: 'bubble', label: 'Bubble', hint: '' },
    { value: 'plain', label: 'Plain', hint: '' },
  ],
};

describe('controlConfigPayload', () => {
  it('keeps the vocabulary of a text control whose label was edited', () => {
    expect(controlConfigPayload('text', {}, look)).toEqual(look);
  });

  it('keeps webfont on a text control and enabled_if on a color control', () => {
    expect(controlConfigPayload('text', {}, { webfont: true })).toEqual({ webfont: true });
    expect(controlConfigPayload('color', {}, { enabled_if: ['c:look != plain'] })).toEqual({ enabled_if: ['c:look != plain'] });
  });

  it('rebuilds the number fields from the form and keeps the rest of the row', () => {
    const existing = { min: 0, max: 100, step: 1, reset_value: 50, random: false, random_interval: null, enabled_if: ['c:spawn = cannon'] };

    expect(controlConfigPayload('number', { min: 0, max: 80, step: 1, reset_value: 50, random: false }, existing)).toEqual({
      enabled_if: ['c:spawn = cannon'],
      min: 0,
      max: 80,
      step: 1,
      reset_value: 50,
      random: false,
      random_interval: null,
    });
  });

  it('sends null for a text control with nothing to keep, as before', () => {
    expect(controlConfigPayload('text', {}, null)).toBeNull();
    expect(controlConfigPayload('boolean', {}, {})).toBeNull();
  });

  it('does not carry the fields of another type into the one being saved', () => {
    expect(controlConfigPayload('text', {}, { min: 0, max: 10, mode: 'countup', running: true, choices: ['a'] })).toEqual({ choices: ['a'] });
  });

  it('rebuilds a timer from the form alone, as before', () => {
    expect(controlConfigPayload('timer', { mode: 'countup' }, { running: true, started_at: 'x', offset_seconds: 9 })).toEqual({
      mode: 'countup',
      base_seconds: 0,
      target_datetime: null,
      offset_seconds: 0,
      running: false,
      started_at: null,
    });
  });
});
