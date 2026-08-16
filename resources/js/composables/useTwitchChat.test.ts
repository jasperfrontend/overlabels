import { describe, expect, it } from 'vitest';
import { clampWindow } from './useTwitchChat';

/*
 * Only the pure part is covered here. The socket itself needs a WebSocket and a
 * DOM, and the suite runs in node by deliberate choice - see the testing note in
 * CLAUDE.md.
 */

describe('clampWindow', () => {
  it('keeps a sensible size as-is', () => {
    expect(clampWindow(1)).toBe(1);
    expect(clampWindow(4)).toBe(4);
    expect(clampWindow(50)).toBe(50);
  });

  it('falls back to the default rather than to one when the value is not a number', () => {
    // THE case that matters. `Number(json.chat_window)` against a payload from
    // an older server is NaN, and clamping that to the low bound would pin
    // every overlay to a one-message feed instead of leaving it at 50.
    expect(clampWindow(NaN)).toBe(50);
    expect(clampWindow(Number(undefined))).toBe(50);
    expect(clampWindow(Number('nonsense'))).toBe(50);
    expect(clampWindow(Infinity)).toBe(50);
  });

  it('clamps to the ceiling, which matches FOREACH_CAP_MAX server-side', () => {
    expect(clampWindow(51)).toBe(50);
    expect(clampWindow(9999)).toBe(50);
  });

  it('clamps to at least one, since a zero-message feed is never intended', () => {
    expect(clampWindow(0)).toBe(1);
    expect(clampWindow(-10)).toBe(1);
  });

  it('floors a fractional size', () => {
    expect(clampWindow(4.9)).toBe(4);
  });
});
