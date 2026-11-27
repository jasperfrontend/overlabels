/**
 * The snowfall, as pure functions: what a flake is, how it falls, how the
 * overlay's custom properties become settings, and what a snowflake crystal
 * looks like. No DOM and no three.js, so all of it is pinned by snow.test.ts;
 * snowScene.ts only draws what this file decides.
 *
 * SPACE. Everything moves in a virtual screen 1080 units tall and as wide as
 * the real one's aspect ratio makes it, so a flake falls the same share of
 * the screen per second in a 720p preview frame as in a 1440p OBS canvas.
 *
 * WHY IT LOOKS NATURAL. Every flake gets a depth when it is born. Near flakes
 * are bigger, brighter and fall faster; far ones are small, faint and slow,
 * and each one also sways on its own rhythm. The streamer's Speed and Size
 * are FACTORS on top of that: they scale every flake's own speed and size,
 * so a slow snowfall is still a crowd of flakes at different speeds, never
 * one sheet moving in step.
 */

export type SnowShape = 'round' | 'square' | 'snowflake';

export interface SnowSettings {
  /** Flakes on screen at once. */
  count: number;
  /** Fall speed factor, 1 = as designed. */
  speed: number;
  /** Size factor, 1 = as designed. */
  size: number;
  shape: SnowShape;
  color: string;
  color2: string;
  /** Degrees; 0 = first colour at the top of each flake, turned clockwise. */
  angle: number;
}

export interface Flake {
  /** Horizontal anchor as a fraction of the screen width; the sway is added on top. */
  x: number;
  /** Vertical centre in virtual units (0 = top edge, VIRTUAL_HEIGHT = bottom edge). */
  y: number;
  /** 0 = far away, 1 = right in front of the camera. */
  depth: number;
  /** This flake's own speed, before the streamer's factor; averages about 1. */
  speed: number;
  /** 0..1 along the shape's size range, before the streamer's factor. */
  sizeT: number;
  /** Sway: phase (radians), rate (radians per second) and reach (virtual units). */
  phase: number;
  rate: number;
  reach: number;
  /** Current turn and turn rate in radians, for the snowflake crystals. */
  turn: number;
  spin: number;
  /** Which crystal from the atlas this flake wears. */
  variant: number;
  /** 0..1 opacity. */
  alpha: number;
}

export const VIRTUAL_HEIGHT = 1080;

/** Virtual units per second a flake of speed 1 falls at a factor of 1: the screen in about eleven seconds. */
export const BASE_FALL = 95;

/** The most flakes the scene allocates; the Amount control's ceiling. */
export const MAX_FLAKES = 3000;

/** How many different crystals the snowflake look draws. */
export const CRYSTAL_VARIANTS = 16;

/** Smallest and largest flake per shape, in virtual units, at a size factor of 1. */
export const SIZE_RANGE: Record<SnowShape, [number, number]> = {
  round: [3, 10],
  square: [2, 7],
  snowflake: [14, 40],
};

export const DEFAULT_SETTINGS: SnowSettings = {
  count: 250,
  speed: 1,
  size: 1,
  shape: 'round',
  color: '#ffffff',
  color2: '#ffffff',
  angle: 0,
};

/** A small seeded generator (mulberry32), so a crystal or a test is the same every time. */
export function seededRandom(seed: number): () => number {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

/**
 * Read the settings from the placeholder's custom properties. `get` returns a
 * property's raw value (`getComputedStyle(el).getPropertyValue`). Anything
 * missing or malformed falls back to the default, so a half-typed control in
 * the editor never stops the snow.
 *
 *   --snow-count   flakes on screen, 0-3000            (default 250)
 *   --snow-speed   fall speed in percent, 0-500         (default 100)
 *   --snow-size    flake size in percent, 10-500        (default 100)
 *   --snow-shape   round | square | snowflake           (default round)
 *   --snow-color   first colour of each flake           (default #ffffff)
 *   --snow-color-2 second colour of each flake          (default #ffffff)
 *   --snow-angle   gradient angle in degrees            (default 0)
 */
export function readSnowSettings(get: (name: string) => string): SnowSettings {
  const prop = (name: string): string =>
    get(name)
      .trim()
      .replace(/^["']|["']$/g, '');
  const num = (name: string, fallback: number, min: number, max: number): number => {
    const raw = prop(name);
    const value = Number.parseFloat(raw);
    return raw !== '' && Number.isFinite(value) ? clamp(value, min, max) : fallback;
  };
  const shape = prop('--snow-shape').toLowerCase();

  return {
    count: Math.round(num('--snow-count', DEFAULT_SETTINGS.count, 0, MAX_FLAKES)),
    speed: num('--snow-speed', 100, 0, 500) / 100,
    size: num('--snow-size', 100, 10, 500) / 100,
    shape: shape === 'square' || shape === 'snowflake' || shape === 'round' ? shape : DEFAULT_SETTINGS.shape,
    color: prop('--snow-color') || DEFAULT_SETTINGS.color,
    color2: prop('--snow-color-2') || prop('--snow-color') || DEFAULT_SETTINGS.color2,
    angle: num('--snow-angle', 0, -3600, 3600),
  };
}

/**
 * A new flake. `anywhere` scatters it over the whole screen (the first
 * snowfall, so the overlay does not open on an empty sky); otherwise it is
 * born just above the top edge.
 */
export function createFlake(random: () => number, anywhere: boolean): Flake {
  // Squaring leans the crowd toward far, small flakes, the way a real
  // snowfall has many more distant flakes than near ones.
  const depth = random() ** 2;
  const flake: Flake = {
    x: random(),
    y: 0,
    depth,
    speed: 0.55 + depth * 0.9 + (random() - 0.5) * 0.3,
    sizeT: clamp(depth * (0.8 + random() * 0.4), 0, 1),
    phase: random() * Math.PI * 2,
    rate: 0.4 + random() * 0.9,
    reach: 6 + random() * 18 + depth * 10,
    turn: random() * Math.PI * 2,
    spin: (random() - 0.5) * 1.2,
    variant: Math.floor(random() * CRYSTAL_VARIANTS),
    alpha: 0.5 + depth * 0.5,
  };
  flake.y = anywhere ? random() * VIRTUAL_HEIGHT : -SIZE_RANGE.snowflake[1] - random() * 60;
  return flake;
}

/** The flake's size in virtual units for a shape and size factor. */
export function flakeSize(flake: Flake, shape: SnowShape, factor: number): number {
  const [min, max] = SIZE_RANGE[shape];
  return (min + (max - min) * flake.sizeT) * factor;
}

/**
 * Move one flake on by `dt` seconds. A flake that has fallen past the bottom
 * edge is born again above the top at a new spot, keeping its depth and
 * speed, so the crowd's mix never changes.
 */
export function stepFlake(flake: Flake, dt: number, speedFactor: number, sizeUnits: number, random: () => number): void {
  flake.y += flake.speed * speedFactor * BASE_FALL * dt;
  flake.phase += flake.rate * dt;
  flake.turn += flake.spin * dt * Math.max(0.25, speedFactor);
  if (flake.y - sizeUnits / 2 > VIRTUAL_HEIGHT) {
    flake.y = -sizeUnits / 2 - random() * 40;
    flake.x = random();
    flake.phase = random() * Math.PI * 2;
  }
}

/** Where the flake is drawn across the screen, in virtual units, sway included. */
export function flakeX(flake: Flake, virtualWidth: number): number {
  return flake.x * virtualWidth + Math.sin(flake.phase) * flake.reach;
}

/**
 * The gradient across one flake, the way Chat Emote Bubbles defines it: at 0
 * the first colour is at the top and the second at the bottom, and the angle
 * turns that clockwise (90 runs right to left, 180 bottom to top). Returns
 * the unit direction the gradient runs toward in screen coordinates (y down)
 * and the length of the gradient line across a unit square, exactly CSS's
 * `linear-gradient(calc(180deg + angle), ...)`.
 */
export function gradientLine(angleDeg: number): { x: number; y: number; length: number } {
  const a = (angleDeg * Math.PI) / 180;
  const x = -Math.sin(a);
  const y = Math.cos(a);
  return { x, y, length: Math.abs(x) + Math.abs(y) };
}

/** Where a point of a flake (-0.5..0.5 each way, y down) sits along the gradient, 0..1. */
export function gradientT(px: number, py: number, angleDeg: number): number {
  const line = gradientLine(angleDeg);
  return clamp((px * line.x + py * line.y) / line.length + 0.5, 0, 1);
}

// ── Crystals ─────────────────────────────────────────────────────────────────

/** A stroke in one arm's own frame: the arm runs from (0,0) up to (0,-1). */
export interface Stroke {
  x1: number;
  y1: number;
  x2: number;
  y2: number;
  width: number;
}

/** A hexagonal plate in the same frame, centred on the arm. */
export interface Plate {
  y: number;
  radius: number;
  filled: boolean;
}

export interface Crystal {
  /** One arm. The drawing repeats it six times, 60 degrees apart. */
  strokes: Stroke[];
  plates: Plate[];
  /** A hexagon at the centre, 0 for none. */
  core: number;
  coreFilled: boolean;
}

/**
 * One snowflake crystal, the way real ones grow: six identical arms, each a
 * spine with side branches at 60 degrees on both sides (shorter toward the
 * tip), sometimes a little plate at the tip, sometimes a hexagon at the
 * centre. Seeded, so variant 7 is the same crystal on every stream.
 */
export function crystal(seed: number): Crystal {
  const random = seededRandom(seed * 7919 + 17);
  // Thick enough to survive being drawn 14 pixels across.
  const spine = 0.09 + random() * 0.05;
  const length = 0.82 + random() * 0.16;
  const strokes: Stroke[] = [{ x1: 0, y1: 0, x2: 0, y2: -length, width: spine }];
  const plates: Plate[] = [];

  // Side branches. Some crystals are bare needles with one pair, most have
  // two to four pairs, and a few are dense ferns.
  const pairs = 1 + Math.floor(random() * 4);
  const lean = ((55 + random() * 15) * Math.PI) / 180;
  const reach = 0.22 + random() * 0.28;
  for (let i = 0; i < pairs; i++) {
    const at = 0.25 + ((i + 0.5) / pairs) * 0.6 * length + (random() - 0.5) * 0.06;
    // Branches shorten toward the tip, which is what makes it read as a crystal.
    const len = reach * (1 - (at / length) * 0.65) * (0.8 + random() * 0.4);
    const width = spine * (0.55 + random() * 0.3);
    const dx = Math.sin(lean) * len;
    const dy = Math.cos(lean) * len;
    strokes.push({ x1: 0, y1: -at, x2: dx, y2: -at - dy, width });
    strokes.push({ x1: 0, y1: -at, x2: -dx, y2: -at - dy, width });
    // Now and then a branch grows a twig of its own.
    if (random() < 0.35) {
      const tx = dx * 0.55;
      const ty = -at - dy * 0.55;
      const twig = len * 0.35;
      strokes.push({ x1: tx, y1: ty, x2: tx, y2: ty - twig, width: width * 0.7 });
      strokes.push({ x1: -tx, y1: ty, x2: -tx, y2: ty - twig, width: width * 0.7 });
    }
  }

  if (random() < 0.45) {
    plates.push({ y: -length, radius: 0.06 + random() * 0.08, filled: random() < 0.5 });
  }
  if (random() < 0.3) {
    plates.push({ y: -length * (0.45 + random() * 0.2), radius: 0.05 + random() * 0.05, filled: true });
  }

  const core = random() < 0.6 ? 0.12 + random() * 0.18 : 0;
  return { strokes, plates, core, coreFilled: random() < 0.5 };
}
