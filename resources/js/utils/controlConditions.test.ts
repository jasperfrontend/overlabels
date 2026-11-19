import { describe, expect, it } from 'vitest';
import { type ConditionSubject, disabledReason, enabledIf } from './controlConditions';

const look: ConditionSubject = {
  key: 'look',
  label: 'Look',
  config: {
    choices: [
      { value: 'bubble', label: 'Bubble', hint: '' },
      { value: 'plain', label: 'Plain', hint: '' },
    ],
  },
  value: 'plain',
};
const speed: ConditionSubject = { key: 'speed', label: 'Float speed', config: { min: 1, max: 10 }, value: '4' };
const shadow: ConditionSubject = { key: 'shadow', label: null, config: null, value: '0' };

function subjects(...list: ConditionSubject[]): Record<string, ConditionSubject> {
  return Object.fromEntries(list.map((s) => [`c:${s.key}`, s]));
}

describe('enabledIf', () => {
  it('reads one condition or a list, and nothing else', () => {
    expect(enabledIf({ enabled_if: 'c:look != plain' })).toEqual(['c:look != plain']);
    expect(enabledIf({ enabled_if: [' c:look != plain ', 'c:speed > 4'] })).toEqual(['c:look != plain', 'c:speed > 4']);
    expect(enabledIf({ enabled_if: [1, '', null] })).toEqual([]);
    expect(enabledIf({ enabled_if: { look: 'plain' } })).toEqual([]);
    expect(enabledIf(null)).toEqual([]);
    expect(enabledIf({})).toEqual([]);
  });
});

describe('disabledReason', () => {
  it('names the choice by its label when a != condition fails', () => {
    expect(disabledReason({ enabled_if: ['c:look != plain'] }, subjects(look))).toBe('Not used while Look is Plain.');
  });

  it('is null once the condition holds', () => {
    expect(disabledReason({ enabled_if: ['c:look != plain'] }, subjects({ ...look, value: 'bubble' }))).toBeNull();
  });

  it('needs every condition in a list, and names the first that fails', () => {
    const config = { enabled_if: ['c:look = bubble', 'c:speed > 4'] };

    expect(disabledReason(config, subjects({ ...look, value: 'bubble' }, { ...speed, value: '5' }))).toBeNull();
    expect(disabledReason(config, subjects({ ...look, value: 'bubble' }, speed))).toBe('Only used while Float speed is above 4.');
    expect(disabledReason(config, subjects(look, speed))).toBe('Only used while Look is Bubble.');
  });

  it('compares numbers as numbers, like [[[if:]]]', () => {
    expect(disabledReason({ enabled_if: 'c:speed >= 10' }, subjects({ ...speed, value: '9' }))).toBe('Only used while Float speed is at least 10.');
    expect(disabledReason({ enabled_if: 'c:speed >= 10' }, subjects({ ...speed, value: '10' }))).toBeNull();
  });

  it('reads a bare key as on or off, falling back to the key for a label', () => {
    expect(disabledReason({ enabled_if: 'c:shadow' }, subjects(shadow))).toBe('Only used while shadow is on.');
    expect(disabledReason({ enabled_if: 'c:shadow' }, subjects({ ...shadow, value: '1' }))).toBeNull();
  });

  it('ignores a condition about a control that is not here, so a bad declaration leaves the knob enabled', () => {
    expect(disabledReason({ enabled_if: ['c:nope == x'] }, subjects(look))).toBeNull();
    expect(disabledReason({ enabled_if: ['look != plain'] }, subjects(look))).toBeNull();
  });
});
