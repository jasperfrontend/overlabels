import { describe, expect, it } from 'vitest';
import { choiceHint, controlChoices } from './controlChoices';

describe('controlChoices', () => {
  it('reads the recipe shape: value, label and hint per entry, in order', () => {
    const choices = controlChoices({
      choices: [
        { value: 'bubble', label: 'Bubble', hint: 'A soap bubble.' },
        { value: 'snow', label: 'Snowflake', hint: 'A snowflake behind the emote.' },
      ],
    });

    expect(choices).toEqual([
      { value: 'bubble', label: 'Bubble', hint: 'A soap bubble.' },
      { value: 'snow', label: 'Snowflake', hint: 'A snowflake behind the emote.' },
    ]);
  });

  it('accepts a bare list of strings, each its own label with no hint', () => {
    expect(controlChoices({ choices: ['up', 'down'] })).toEqual([
      { value: 'up', label: 'up', hint: '' },
      { value: 'down', label: 'down', hint: '' },
    ]);
  });

  it('fills a missing label with the value and a missing hint with nothing', () => {
    expect(controlChoices({ choices: [{ value: 'edge' }, { value: 'random', label: '' }] })).toEqual([
      { value: 'edge', label: 'edge', hint: '' },
      { value: 'random', label: 'random', hint: '' },
    ]);
  });

  it('drops entries with no usable value and repeats of one already seen', () => {
    const choices = controlChoices({
      choices: [{ value: 'up' }, { value: '' }, { label: 'no value' }, 7, null, 'up', { value: 'down' }],
    });

    expect(choices.map((choice) => choice.value)).toEqual(['up', 'down']);
  });

  it('is empty for a control with no vocabulary, a null config, or a malformed one', () => {
    expect(controlChoices(null)).toEqual([]);
    expect(controlChoices(undefined)).toEqual([]);
    expect(controlChoices({ min: 0, max: 100 })).toEqual([]);
    expect(controlChoices({ choices: 'bubble,snow' })).toEqual([]);
    expect(controlChoices({ choices: { bubble: 'Bubble' } })).toEqual([]);
    expect(controlChoices('choices')).toEqual([]);
  });
});

describe('choiceHint', () => {
  const choices = controlChoices({
    choices: [
      { value: 'outside', label: 'Outside', hint: 'Floats in from just past the edge.' },
      { value: 'edge', label: 'Edge' },
    ],
  });

  it('answers with the hint of the held value', () => {
    expect(choiceHint(choices, 'outside')).toBe('Floats in from just past the edge.');
  });

  it('answers with nothing for a choice without a hint, and for a value that is not a choice', () => {
    expect(choiceHint(choices, 'edge')).toBe('');
    expect(choiceHint(choices, 'cannon')).toBe('');
  });
});
