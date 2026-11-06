import { describe, expect, it } from 'vitest';
import { boardGeometry, collideBalls, pegCount, pegPosition, slotCount, slotIndex, slotPoints, spawnBall, STEP, stepBall } from './plinkoPhysics';
import { isDropCommand, PLINKO_TAG, replacePlinkoTags, sourceUsesPlinko } from './plinkoTag';

describe('plinko tag pre-pass', () => {
  it('replaces every occurrence with a bracket-free placeholder', () => {
    const result = replacePlinkoTags(`<main>${PLINKO_TAG}</main>`);
    expect(result).toContain('data-plinko-board');
    expect(result).not.toContain('[');
  });

  it('detects usage only on the exact literal', () => {
    expect(sourceUsesPlinko(`x ${PLINKO_TAG} y`)).toBe(true);
    expect(sourceUsesPlinko('[[[plinko_board|round]]]')).toBe(false);
  });
});

describe('the drop command', () => {
  it('matches the command word alone or with words after it, in any case', () => {
    expect(isDropCommand('!drop')).toBe(true);
    expect(isDropCommand('  !DROP')).toBe(true);
    expect(isDropCommand('!drop let me in')).toBe(true);
  });

  it('does not match a longer word, a mid-sentence mention or another command', () => {
    expect(isDropCommand('!dropped')).toBe(false);
    expect(isDropCommand('type !drop')).toBe(false);
    expect(isDropCommand('!stack')).toBe(false);
  });

  it('takes a custom command with or without its !', () => {
    expect(isDropCommand('!plinko', 'plinko')).toBe(true);
    expect(isDropCommand('!plinko', '!plinko')).toBe(true);
    expect(isDropCommand('!drop', 'plinko')).toBe(false);
  });
});

describe('plinko board', () => {
  const g = boardGeometry(800, 860, 14);

  it('gives row r r+3 pegs and the board one more slot than rows', () => {
    expect(pegCount(0)).toBe(3);
    expect(pegCount(13)).toBe(16);
    expect(slotCount(g)).toBe(15);
  });

  it('fits the widest row inside the board', () => {
    const left = pegPosition(g, g.rows - 1, 0).x;
    const right = pegPosition(g, g.rows - 1, pegCount(g.rows - 1) - 1).x;
    expect(left).toBeGreaterThanOrEqual(0);
    expect(right).toBeLessThanOrEqual(g.width);
    expect(g.binFloor).toBeLessThanOrEqual(g.height);
  });

  it('pays the most at the edges and the least in the middle, symmetrically', () => {
    expect(slotPoints(14, 7)).toBe(5);
    expect(slotPoints(14, 0)).toBe(1000);
    expect(slotPoints(14, 14)).toBe(1000);
    for (let k = 0; k <= 14; k++) expect(slotPoints(14, k)).toBe(slotPoints(14, 14 - k));
  });

  it('maps any x to a slot that exists', () => {
    expect(slotIndex(g, -1000)).toBe(0);
    expect(slotIndex(g, 5000)).toBe(14);
    expect(slotIndex(g, g.cx)).toBe(7);
  });
});

describe('plinko physics', () => {
  const g = boardGeometry(800, 860, 14);

  function drop(id: string): { slot: number; seconds: number; hits: number; maxOffset: number } {
    const ball = spawnBall(g, id);
    let age = 0;
    let hits = 0;
    let maxOffset = 0;
    while (ball.landedAt === null && age < 30) {
      age += STEP;
      hits += stepBall(g, ball, age).length;
      if (ball.y < g.binTop) {
        const edge = ((Math.max(0, (ball.y - g.top) / g.rowHeight) + 2) / 2) * g.spacing;
        maxOffset = Math.max(maxOffset, Math.abs(ball.x - g.cx) - edge);
      }
    }
    return { slot: ball.slot ?? -1, seconds: ball.landedAt ?? Infinity, hits, maxOffset };
  }

  const runs = Array.from({ length: 400 }, (_, i) => drop(`msg-${i}`));

  it('lands every ball in a real slot within a few seconds', () => {
    for (const run of runs) {
      expect(run.slot).toBeGreaterThanOrEqual(0);
      expect(run.slot).toBeLessThanOrEqual(14);
      expect(run.seconds).toBeLessThan(10);
    }
  });

  it('bounces off pegs on the way down', () => {
    const median = runs.map((r) => r.hits).sort((a, b) => a - b)[runs.length / 2];
    expect(median).toBeGreaterThanOrEqual(6);
  });

  it('never lets a ball outside the pyramid', () => {
    for (const run of runs) expect(run.maxOffset).toBeLessThanOrEqual(0.001);
  });

  it('spreads landings like a Galton board: mostly the middle, rarely the edges', () => {
    const middle = runs.filter((r) => Math.abs(r.slot - 7) <= 2).length;
    const edges = runs.filter((r) => r.slot <= 1 || r.slot >= 13).length;
    const left = runs.filter((r) => r.slot < 7).length;
    const right = runs.filter((r) => r.slot > 7).length;
    expect(middle).toBeGreaterThan(runs.length * 0.4);
    expect(edges).toBeLessThan(runs.length * 0.1);
    expect(Math.abs(left - right)).toBeLessThan(runs.length * 0.15);
  });

  it('replays the same message the same way', () => {
    expect(drop('same-id')).toEqual(drop('same-id'));
  });
});

describe('balls hitting balls', () => {
  const g = boardGeometry(800, 860, 14);

  it('pushes two overlapping falling balls apart and sends them away from each other', () => {
    const a = spawnBall(g, 'a');
    const b = spawnBall(g, 'b');
    Object.assign(a, { x: 400, y: 300, vx: 50, vy: 0 });
    Object.assign(b, { x: 405, y: 300, vx: -50, vy: 0 });
    collideBalls(g, [a, b]);
    expect(b.x - a.x).toBeCloseTo(g.ballRadius * 2, 5);
    expect(a.vx).toBeLessThan(0);
    expect(b.vx).toBeGreaterThan(0);
  });

  it('leaves a ball that has landed where it is', () => {
    const a = spawnBall(g, 'a');
    const b = spawnBall(g, 'b');
    Object.assign(a, { x: 400, y: 300, landedAt: 4 });
    Object.assign(b, { x: 402, y: 300 });
    collideBalls(g, [a, b]);
    expect(a.x).toBe(400);
    expect(b.x).toBe(402);
  });
});
