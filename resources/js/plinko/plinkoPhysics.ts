/**
 * Plinko physics: a pyramid of pegs, balls under gravity, bins at the bottom.
 *
 * Pure functions over plain objects, no DOM and no clock, so the board module
 * can step them every frame and the tests can step them in a loop. Units are
 * CSS pixels and seconds. Row r (0 at the top) has r + 3 pegs; the last row's
 * gaps are the bins, so `rows` rows of pegs make `rows + 1` slots.
 *
 * Nothing here needs to be reproducible across viewers: the overlay is drawn
 * once, in the streamer's OBS, and that one picture is what every viewer sees.
 * Each ball still gets its own seeded random source so a test can replay it.
 */

export interface BoardGeometry {
  width: number;
  height: number;
  rows: number;
  /** Horizontal distance between neighbouring pegs. */
  spacing: number;
  rowHeight: number;
  /** y of the top row of pegs. */
  top: number;
  /** x of the board's centre line. */
  cx: number;
  pegRadius: number;
  ballRadius: number;
  /** y where the bins begin (the ball can no longer change slot below it). */
  binTop: number;
  /** y of the bins' floor. */
  binFloor: number;
}

export interface Ball {
  id: string;
  x: number;
  y: number;
  vx: number;
  vy: number;
  /** Seconds since spawn at landing, or null while falling. */
  landedAt: number | null;
  slot: number | null;
  rng: () => number;
}

export interface PegHit {
  row: number;
  index: number;
}

/** Points by distance from the middle slot. The edges are rare, so they pay most. */
export const SLOT_POINTS = [5, 10, 20, 50, 100, 250, 500, 1000] as const;

const RESTITUTION = 0.45;
/** Gravity, in peg spacings per second squared. */
const GRAVITY = 26;
/** Fastest a ball may travel, in spacings per second. Keeps a step from tunnelling through a peg. */
const MAX_SPEED = 22;
/** Fixed physics step. */
export const STEP = 1 / 240;
/** Room above the top row, in spacings: a new ball and its name tag must fit on screen. */
const HEADROOM = 2.6;

export function boardGeometry(width: number, height: number, rows: number): BoardGeometry {
  const r = Math.max(4, Math.min(24, Math.round(rows)));
  // Height the board needs, in spacings: room above the top row to spawn, the
  // rows themselves, the bins, and a strip under them for the slot labels.
  const heightInSpacings = HEADROOM + (r - 1) * 0.87 + 1.5 + 0.9;
  const spacing = Math.max(8, Math.min((width * 0.96) / (r + 1), height / heightInSpacings));
  const rowHeight = spacing * 0.87;
  const used = heightInSpacings * spacing;
  const top = (height - used) / 2 + HEADROOM * spacing;
  const binTop = top + (r - 1) * rowHeight + spacing * 0.35;

  return {
    width,
    height,
    rows: r,
    spacing,
    rowHeight,
    top,
    cx: width / 2,
    pegRadius: spacing * 0.1,
    ballRadius: spacing * 0.27,
    binTop,
    binFloor: binTop + spacing * 1.3,
  };
}

export function pegCount(row: number): number {
  return row + 3;
}

export function pegPosition(g: BoardGeometry, row: number, index: number): { x: number; y: number } {
  return { x: g.cx + (index - (row + 2) / 2) * g.spacing, y: g.top + row * g.rowHeight };
}

export function slotCount(g: BoardGeometry): number {
  return g.rows + 1;
}

export function slotCenter(g: BoardGeometry, slot: number): number {
  return g.cx + (slot - g.rows / 2) * g.spacing;
}

export function slotIndex(g: BoardGeometry, x: number): number {
  return Math.max(0, Math.min(g.rows, Math.round((x - g.cx) / g.spacing + g.rows / 2)));
}

export function slotPoints(rows: number, slot: number): number {
  const distance = Math.floor(Math.abs(slot - rows / 2));
  return SLOT_POINTS[Math.min(distance, SLOT_POINTS.length - 1)];
}

/** A small, fast seeded generator (mulberry32). */
export function seededRandom(seed: string): () => number {
  let h = 1779033703 ^ seed.length;
  for (let i = 0; i < seed.length; i++) {
    h = Math.imul(h ^ seed.charCodeAt(i), 3432918353);
    h = (h << 13) | (h >>> 19);
  }
  let a = h >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export function spawnBall(g: BoardGeometry, id: string): Ball {
  const rng = seededRandom(id);
  return {
    id,
    x: g.cx + (rng() - 0.5) * g.spacing * 1.6,
    y: g.top - g.spacing * 1.0,
    vx: (rng() - 0.5) * g.spacing * 0.6,
    vy: 0,
    landedAt: null,
    slot: null,
    rng,
  };
}

/**
 * Advance one ball by one fixed step. `age` is the ball's seconds since spawn
 * AFTER this step, recorded on landing. Returns the pegs it touched.
 */
export function stepBall(g: BoardGeometry, ball: Ball, age: number): PegHit[] {
  if (ball.landedAt !== null) return [];
  const s = g.spacing;
  const hits: PegHit[] = [];

  ball.vy += GRAVITY * s * STEP;
  ball.vx *= 1 - 0.4 * STEP;
  const speed = Math.hypot(ball.vx, ball.vy);
  const max = MAX_SPEED * s;
  if (speed > max) {
    ball.vx *= max / speed;
    ball.vy *= max / speed;
  }
  ball.x += ball.vx * STEP;
  ball.y += ball.vy * STEP;

  if (ball.y < g.binTop) {
    // Pegs: only the few around the ball can touch it.
    const reach = g.ballRadius + g.pegRadius;
    const nearRow = Math.round((ball.y - g.top) / g.rowHeight);
    for (let row = nearRow - 1; row <= nearRow + 1; row++) {
      if (row < 0 || row >= g.rows) continue;
      const nearIndex = Math.round((ball.x - g.cx) / s + (row + 2) / 2);
      for (let index = nearIndex - 1; index <= nearIndex + 1; index++) {
        if (index < 0 || index >= pegCount(row)) continue;
        const peg = pegPosition(g, row, index);
        const dx = ball.x - peg.x;
        const dy = ball.y - peg.y;
        const dist2 = dx * dx + dy * dy;
        if (dist2 >= reach * reach) continue;

        const dist = Math.sqrt(dist2) || 1e-6;
        const nx = dx / dist;
        const ny = dy / dist;
        ball.x = peg.x + nx * reach;
        ball.y = peg.y + ny * reach;
        const vn = ball.vx * nx + ball.vy * ny;
        if (vn < 0) {
          ball.vx -= (1 + RESTITUTION) * vn * nx;
          ball.vy -= (1 + RESTITUTION) * vn * ny;
          // A little spin off every peg, and a firm nudge when the ball lands
          // dead on top, or it would balance there.
          const kick = (ball.rng() - 0.5) * s * 1.1;
          ball.vx += -ny * kick;
          ball.vy += nx * kick;
          if (Math.abs(nx) < 0.2) ball.vx += (ball.rng() < 0.5 ? -1 : 1) * s * 0.9;
          hits.push({ row, index });
        }
      }
    }

    // The pyramid's sloped sides: never further out than the edge peg of the row the ball is on.
    const rowsDown = Math.max(0, (ball.y - g.top) / g.rowHeight);
    const edge = ((rowsDown + 2) / 2) * s;
    const offset = ball.x - g.cx;
    if (Math.abs(offset) > edge) {
      ball.x = g.cx + Math.sign(offset) * edge;
      if (Math.sign(ball.vx) === Math.sign(offset)) ball.vx = -ball.vx * RESTITUTION;
    }
    return hits;
  }

  // In a bin: the dividers hold it in its slot, the floor stops it.
  if (ball.slot === null) ball.slot = slotIndex(g, ball.x);
  const centre = slotCenter(g, ball.slot);
  const room = s / 2 - g.ballRadius - g.pegRadius;
  if (Math.abs(ball.x - centre) > room) {
    ball.x = centre + Math.sign(ball.x - centre) * room;
    ball.vx = -ball.vx * RESTITUTION;
  }
  const floor = g.binFloor - g.ballRadius;
  if (ball.y >= floor) {
    ball.y = floor;
    ball.vx = 0;
    ball.vy = 0;
    ball.landedAt = age;
  }
  return hits;
}

/**
 * Balls knock each other aside instead of passing through. Equal masses, so a
 * collision swaps the part of their velocities along the line between them.
 * Only falling balls take part: a ball in its bin is already scored and on
 * its way out. All pairs, which is cheap at the board's in-flight cap.
 */
export function collideBalls(g: BoardGeometry, balls: Ball[]): void {
  const reach = g.ballRadius * 2;
  const reach2 = reach * reach;
  for (let i = 0; i < balls.length; i++) {
    const a = balls[i];
    if (a.landedAt !== null || a.y >= g.binTop) continue;
    for (let j = i + 1; j < balls.length; j++) {
      const b = balls[j];
      if (b.landedAt !== null || b.y >= g.binTop) continue;
      const dy = b.y - a.y;
      if (dy > reach || dy < -reach) continue;
      const dx = b.x - a.x;
      const dist2 = dx * dx + dy * dy;
      if (dist2 >= reach2) continue;

      const dist = Math.sqrt(dist2) || 1e-6;
      // Two balls spawned on the same point have no direction between them: pick one.
      const nx = dist2 === 0 ? 1 : dx / dist;
      const ny = dist2 === 0 ? 0 : dy / dist;
      const push = (reach - dist) / 2;
      a.x -= nx * push;
      a.y -= ny * push;
      b.x += nx * push;
      b.y += ny * push;

      const vn = (b.vx - a.vx) * nx + (b.vy - a.vy) * ny;
      if (vn < 0) {
        const impulse = (-(1 + RESTITUTION) * vn) / 2;
        a.vx -= impulse * nx;
        a.vy -= impulse * ny;
        b.vx += impulse * nx;
        b.vy += impulse * ny;
      }
    }
  }
}
