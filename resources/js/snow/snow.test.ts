import { describe, expect, it } from 'vitest';
import {
  BASE_FALL,
  createFlake,
  crystal,
  DEFAULT_SETTINGS,
  flakeSize,
  gradientT,
  MAX_FLAKES,
  readSnowSettings,
  seededRandom,
  SIZE_RANGE,
  stepFlake,
  VIRTUAL_HEIGHT,
} from './snowPhysics';
import { replaceSnowTags, sourceUsesSnow } from './snowTag';

const props = (values: Record<string, string>) => (name: string) => values[name] ?? '';

describe('snow tag', () => {
  it('swaps the literal tag for the placeholder and nothing else', () => {
    const source = '<div class="x">[[[snow_scene]]]</div>';
    expect(sourceUsesSnow(source)).toBe(true);
    expect(replaceSnowTags(source)).toBe('<div class="x"><div class="ol-snow" data-snow-scene></div></div>');
    expect(sourceUsesSnow('[[[snow_scene|upper]]]')).toBe(false);
  });
});

describe('readSnowSettings', () => {
  it('falls back to the defaults when nothing is set', () => {
    expect(readSnowSettings(props({}))).toEqual(DEFAULT_SETTINGS);
  });

  it('reads percentages as factors and clamps them', () => {
    const s = readSnowSettings(props({ '--snow-speed': ' 50', '--snow-size': '9999', '--snow-count': '99999' }));
    expect(s.speed).toBe(0.5);
    expect(s.size).toBe(5);
    expect(s.count).toBe(MAX_FLAKES);
  });

  it('ignores an unknown shape and a half-typed number', () => {
    const s = readSnowSettings(props({ '--snow-shape': 'triangle', '--snow-count': 'abc' }));
    expect(s.shape).toBe('round');
    expect(s.count).toBe(DEFAULT_SETTINGS.count);
    expect(readSnowSettings(props({ '--snow-shape': ' Snowflake ' })).shape).toBe('snowflake');
  });

  it('uses the first colour for the second when only one is set', () => {
    expect(readSnowSettings(props({ '--snow-color': '#bfe3ff' })).color2).toBe('#bfe3ff');
  });
});

describe('flakes', () => {
  it('keeps each flake its own speed: the factor scales, it never evens them out', () => {
    const random = seededRandom(1);
    const a = createFlake(random, true);
    const b = createFlake(random, true);
    const ya = a.y;
    const yb = b.y;
    stepFlake(a, 1, 0.5, 0, () => 0.5);
    stepFlake(b, 1, 0.5, 0, () => 0.5);
    expect(a.y - ya).toBeCloseTo(a.speed * 0.5 * BASE_FALL);
    expect(b.y - yb).toBeCloseTo(b.speed * 0.5 * BASE_FALL);
    expect(a.speed).not.toBeCloseTo(b.speed);
  });

  it('averages a speed of about 1, with near flakes faster than far ones', () => {
    const random = seededRandom(2);
    const flakes = Array.from({ length: 4000 }, () => createFlake(random, true));
    const mean = flakes.reduce((sum, f) => sum + f.speed, 0) / flakes.length;
    expect(mean).toBeGreaterThan(0.75);
    expect(mean).toBeLessThan(1.1);
    const near = flakes.filter((f) => f.depth > 0.8);
    const far = flakes.filter((f) => f.depth < 0.2);
    const avg = (list: typeof flakes, key: 'speed' | 'sizeT') => list.reduce((s, f) => s + f[key], 0) / list.length;
    expect(avg(near, 'speed')).toBeGreaterThan(avg(far, 'speed'));
    expect(avg(near, 'sizeT')).toBeGreaterThan(avg(far, 'sizeT'));
  });

  it('scales its own size by the factor, inside the shape range', () => {
    const flake = createFlake(seededRandom(3), true);
    const [min, max] = SIZE_RANGE.snowflake;
    const size = flakeSize(flake, 'snowflake', 1);
    expect(size).toBeGreaterThanOrEqual(min);
    expect(size).toBeLessThanOrEqual(max);
    expect(flakeSize(flake, 'snowflake', 2)).toBeCloseTo(size * 2);
  });

  it('is born again above the top once it has fallen off the bottom, keeping its speed', () => {
    const flake = createFlake(seededRandom(4), true);
    flake.y = VIRTUAL_HEIGHT + 20;
    const speed = flake.speed;
    stepFlake(flake, 0, 1, 10, () => 0.25);
    expect(flake.y).toBeLessThan(0);
    expect(flake.x).toBe(0.25);
    expect(flake.speed).toBe(speed);
  });

  it('stands still at speed 0', () => {
    const flake = createFlake(seededRandom(5), true);
    const y = flake.y;
    stepFlake(flake, 1, 0, 10, Math.random);
    expect(flake.y).toBe(y);
  });
});

describe('gradient', () => {
  it('runs first colour at the top to second at the bottom at 0, like Chat Emote Bubbles', () => {
    expect(gradientT(0, -0.5, 0)).toBeCloseTo(0);
    expect(gradientT(0, 0.5, 0)).toBeCloseTo(1);
  });

  it('turns clockwise: 90 runs right to left, 180 bottom to top, 270 left to right', () => {
    expect(gradientT(0.5, 0, 90)).toBeCloseTo(0);
    expect(gradientT(-0.5, 0, 90)).toBeCloseTo(1);
    expect(gradientT(0, 0.5, 180)).toBeCloseTo(0);
    expect(gradientT(-0.5, 0, 270)).toBeCloseTo(0);
  });

  it('reaches both colours exactly in the corners at 45 degrees', () => {
    expect(gradientT(0.5, -0.5, 45)).toBeCloseTo(0);
    expect(gradientT(-0.5, 0.5, 45)).toBeCloseTo(1);
  });
});

describe('crystals', () => {
  it('is the same crystal for the same variant, and different ones across variants', () => {
    expect(crystal(7)).toEqual(crystal(7));
    const shapes = new Set(Array.from({ length: 16 }, (_, v) => JSON.stringify(crystal(v))));
    expect(shapes.size).toBe(16);
  });

  it('grows its side branches in mirrored pairs and stays inside its cell', () => {
    for (let v = 0; v < 16; v++) {
      const { strokes, plates, core } = crystal(v);
      const branches = strokes.slice(1);
      expect(branches.length % 2).toBe(0);
      for (let i = 0; i < branches.length; i += 2) {
        expect(branches[i].x2).toBeCloseTo(-branches[i + 1].x2);
        expect(branches[i].y2).toBeCloseTo(branches[i + 1].y2);
      }
      for (const s of strokes) {
        expect(Math.hypot(s.x2, s.y2)).toBeLessThanOrEqual(1.05);
      }
      for (const p of plates) expect(Math.abs(p.y) + p.radius).toBeLessThanOrEqual(1.1);
      expect(core).toBeLessThan(0.31);
    }
  });
});
