import { describe, expect, it } from 'vitest';
import { appendBlock, blocksFromData, clampTowerWindow, DEFAULT_TOWER_WINDOW, toBlock, withTowerSlots, type TowerBlock } from './towerSlots';

function block(position: number, login = `builder_${position}`): TowerBlock {
  return {
    name: login.toUpperCase(),
    login,
    color: '#1E90FF',
    position: String(position),
    offset: '0.25',
    x: String(0.25 * position),
    record: '',
    at: '1757500000',
  };
}

describe('clampTowerWindow', () => {
  it('passes a sane value through', () => {
    expect(clampTowerWindow(25)).toBe(25);
    expect(clampTowerWindow('10')).toBe(10);
  });

  it('falls back to the DEFAULT on NaN, never to 1', () => {
    // An older server does not ship tower_window; Number(undefined) is NaN.
    expect(clampTowerWindow(undefined)).toBe(DEFAULT_TOWER_WINDOW);
    expect(clampTowerWindow('nope')).toBe(DEFAULT_TOWER_WINDOW);
  });

  it('clamps to the ceiling and floor', () => {
    expect(clampTowerWindow(9999)).toBe(DEFAULT_TOWER_WINDOW);
    expect(clampTowerWindow(0)).toBe(DEFAULT_TOWER_WINDOW);
  });
});

describe('toBlock', () => {
  it('normalizes a broadcast block and stringifies stray values', () => {
    const result = toBlock({ ...block(3), at: 1757500000 as unknown as string, position: 3 as unknown as string });
    expect(result?.at).toBe('1757500000');
    expect(result?.position).toBe('3');
  });

  it('rejects payloads without a login or a numeric position', () => {
    expect(toBlock(null)).toBeNull();
    expect(toBlock({})).toBeNull();
    expect(toBlock({ login: 'a' })).toBeNull();
    expect(toBlock({ login: 'a', position: 'top' })).toBeNull();
  });
});

describe('appendBlock', () => {
  it('lands a block on top - at the END of the window', () => {
    const blocks = appendBlock([block(1), block(2)], block(3), 50);
    expect(blocks.map((b) => b.position)).toEqual(['1', '2', '3']);
  });

  it('trims from the BOTTOM so the top of the tower survives', () => {
    const blocks = appendBlock([block(1), block(2), block(3)], block(4), 3);
    expect(blocks.map((b) => b.position)).toEqual(['2', '3', '4']);
  });

  it('replaces a block at a position it already holds - a replayed delta changes nothing', () => {
    const blocks = appendBlock([block(1), block(2)], block(2, 'someone_else'), 50);
    expect(blocks.map((b) => b.login)).toEqual(['builder_1', 'someone_else']);
  });
});

describe('withTowerSlots and blocksFromData', () => {
  it('round-trips a window through the flat data shape, count being the true height', () => {
    const data = withTowerSlots({ other: 'kept' }, [block(6), block(7)], 7);

    expect(data.other).toBe('kept');
    expect(data['tower.count']).toBe('7');
    expect(data['tower.0.position']).toBe('6');
    expect(data['tower.1.login']).toBe('builder_7');
    expect(blocksFromData(data).map((b) => b.position)).toEqual(['6', '7']);
  });

  it('drops every stale slot before writing - a topple cannot leave rubble behind', () => {
    const before = withTowerSlots({}, [block(1), block(2), block(3)], 3);
    const after = withTowerSlots(before, [], 0);

    expect(after['tower.count']).toBe('0');
    expect(after['tower.0.login']).toBeUndefined();
    expect(after['tower.2.x']).toBeUndefined();
    expect(blocksFromData(after)).toEqual([]);
  });
});
