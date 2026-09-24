import { describe, expect, it } from 'vitest';
import { formatKeyCombination, parseKeyCombination, pressedKeys, resolveKeystroke, type Shortcut } from './useKeyboardShortcuts';

const noop = () => {};

function shortcut(combination: string, extra: Partial<Shortcut> = {}): Shortcut {
  return { steps: parseKeyCombination(combination), callback: noop, ...extra };
}

describe('parseKeyCombination', () => {
  it('reads a single step with modifiers', () => {
    expect(parseKeyCombination('ctrl+s')).toEqual([['ctrl', 's']]);
    expect(parseKeyCombination('Ctrl+Shift+F')).toEqual([['ctrl', 'shift', 'f']]);
  });

  it('names the space bar as a key inside a step', () => {
    expect(parseKeyCombination('ctrl+space')).toEqual([['ctrl', ' ']]);
  });

  it('reads a chord as one step per whitespace-separated token', () => {
    expect(parseKeyCombination('g o')).toEqual([['g'], ['o']]);
    expect(parseKeyCombination('  g   shift+o ')).toEqual([['g'], ['shift', 'o']]);
  });
});

describe('formatKeyCombination', () => {
  it('renders a single step the way it always did', () => {
    expect(formatKeyCombination([['ctrl', 's']])).toBe('Ctrl+S');
    expect(formatKeyCombination([['ctrl', ' ']])).toBe('Ctrl+Space');
    expect(formatKeyCombination([['e']])).toBe('E');
  });

  it('joins chord steps with "then"', () => {
    expect(formatKeyCombination([['g'], ['o']])).toBe('G then O');
  });
});

describe('pressedKeys', () => {
  it('lists modifiers in a fixed order and lowercases the key', () => {
    expect(pressedKeys({ ctrlKey: true, altKey: false, shiftKey: true, metaKey: false, key: 'S' })).toEqual(['ctrl', 'shift', 's']);
    expect(pressedKeys({ ctrlKey: false, altKey: false, shiftKey: false, metaKey: false, key: 'G' })).toEqual(['g']);
  });
});

describe('resolveKeystroke', () => {
  const overlays = shortcut('g o');
  const streams = shortcut('g s');
  const edit = shortcut('e');
  const save = shortcut('ctrl+s');
  const all = [save, edit, overlays, streams];

  it('fires a single-step shortcut with no prefix armed', () => {
    expect(resolveKeystroke(all, [], ['ctrl', 's'])).toEqual({ kind: 'fire', shortcut: save });
    expect(resolveKeystroke(all, [], ['e'])).toEqual({ kind: 'fire', shortcut: edit });
  });

  it('arms a prefix on the first key of a chord instead of firing', () => {
    expect(resolveKeystroke(all, [], ['g'])).toEqual({ kind: 'prefix' });
  });

  it('fires the chord whose second step matches the armed prefix', () => {
    expect(resolveKeystroke(all, [['g']], ['o'])).toEqual({ kind: 'fire', shortcut: overlays });
    expect(resolveKeystroke(all, [['g']], ['s'])).toEqual({ kind: 'fire', shortcut: streams });
  });

  it('is nothing when the armed prefix is followed by a key no chord continues with', () => {
    // 'e' alone is a shortcut, but after G it completes no chord and must not fire.
    expect(resolveKeystroke(all, [['g']], ['e'])).toEqual({ kind: 'none' });
  });

  it('is nothing for a key no shortcut starts with', () => {
    expect(resolveKeystroke(all, [], ['x'])).toEqual({ kind: 'none' });
  });

  it('does not let a modifier variant complete a chord', () => {
    expect(resolveKeystroke(all, [['g']], ['shift', 'o'])).toEqual({ kind: 'none' });
    expect(resolveKeystroke(all, [], ['ctrl', 'g'])).toEqual({ kind: 'none' });
  });

  it('lets a complete match win over a longer chord sharing its start', () => {
    const bareG = shortcut('g');
    expect(resolveKeystroke([overlays, bareG], [], ['g'])).toEqual({ kind: 'fire', shortcut: bareG });
  });
});
