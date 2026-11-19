import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { angleColorKeys, angleFromPointer, gradientCss, normaliseAngle } from './angleKnob';

describe('angleColorKeys', () => {
  it('reads exactly two non-empty keys, and nothing else', () => {
    expect(angleColorKeys({ angle_colors: ['bubble_color', 'bubble_color_2'] })).toEqual(['bubble_color', 'bubble_color_2']);
    expect(angleColorKeys({ angle_colors: ['bubble_color'] })).toBeNull();
    expect(angleColorKeys({ angle_colors: ['a', 'b', 'c'] })).toBeNull();
    expect(angleColorKeys({ angle_colors: ['a', ''] })).toBeNull();
    expect(angleColorKeys({ angle_colors: ['a', 2] })).toBeNull();
    expect(angleColorKeys({ angle_colors: 'a,b' })).toBeNull();
    expect(angleColorKeys({ min: 0 })).toBeNull();
    expect(angleColorKeys(null)).toBeNull();
  });
});

describe('angleFromPointer', () => {
  it('puts the first color where the pointer is, clockwise from twelve o clock', () => {
    expect(angleFromPointer(0, -10)).toBe(0);
    expect(angleFromPointer(10, 0)).toBe(90);
    expect(angleFromPointer(0, 10)).toBe(180);
    expect(angleFromPointer(-10, 0)).toBe(270);
    expect(angleFromPointer(-10, -10)).toBe(315);
  });

  it('snaps to a step when asked', () => {
    expect(angleFromPointer(10, -4)).toBe(68);
    expect(angleFromPointer(10, -4, 15)).toBe(75);
    expect(angleFromPointer(-1, -100, 15)).toBe(0);
  });

  it('answers 0 for the exact centre rather than NaN', () => {
    expect(angleFromPointer(0, 0)).toBe(0);
  });
});

describe('normaliseAngle', () => {
  it('wraps into [0, 360) and rounds', () => {
    expect(normaliseAngle(360)).toBe(0);
    expect(normaliseAngle(-90)).toBe(270);
    expect(normaliseAngle(721.4)).toBe(1);
    expect(normaliseAngle(Number.NaN)).toBe(0);
  });
});

describe('gradientCss', () => {
  it('paints the first color at the angle, the way the overlay does', () => {
    // 0: first color at the top, so CSS points down (180deg).
    expect(gradientCss(0, '#fff', '#000')).toBe('linear-gradient(180deg, #fff, #000)');
    // 90: first color at the right, so CSS points left (270deg).
    expect(gradientCss(90, '#fff', '#000')).toBe('linear-gradient(270deg, #fff, #000)');
    expect(gradientCss(180, '#fff', '#000')).toBe('linear-gradient(0deg, #fff, #000)');
  });

  it('uses the same offset as the Chat Emote Bubbles overlay, so the circle never disagrees with the stream', () => {
    const recipe = readFileSync(resolve(__dirname, '../../recipes/chat-emote-bubbles/bubbles.md'), 'utf8');

    expect(recipe).toContain('--flow: calc(180deg + [[[c:gradient_angle]]]deg);');
    expect(recipe).toContain('--fill: linear-gradient(var(--flow), var(--tint), var(--tint2));');
  });
});
