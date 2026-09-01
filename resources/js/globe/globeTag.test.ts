import { describe, expect, it } from 'vitest';
import { GLOBE_TAG, replaceGlobeTags, sourceUsesGlobe } from './globeTag';
import { isLandCell, landDots, latLngToUnitVector, MASK_COLS, MASK_ROWS } from './landmask';

describe('globe tag pre-pass', () => {
  it('replaces every occurrence with the placeholder div', () => {
    const source = `<main>${GLOBE_TAG}</main><aside>${GLOBE_TAG}</aside>`;
    const result = replaceGlobeTags(source);

    expect(result).not.toContain(GLOBE_TAG);
    expect(result.match(/data-checkin-globe/g)).toHaveLength(2);
  });

  it('emits a placeholder with no brackets, so the tag passes never touch it', () => {
    const result = replaceGlobeTags(GLOBE_TAG);
    expect(result).not.toContain('[');
    expect(result).not.toContain(']');
  });

  it('leaves other tags alone', () => {
    const source = '<div>[[[channel_name]]] [[[c:checkin:checkins_this_stream]]]</div>';
    expect(replaceGlobeTags(source)).toBe(source);
  });

  it('detects usage only on the exact literal', () => {
    expect(sourceUsesGlobe(`x ${GLOBE_TAG} y`)).toBe(true);
    expect(sourceUsesGlobe('[[[checkin_globe|round]]]')).toBe(false);
    expect(sourceUsesGlobe('[[[channel_name]]]')).toBe(false);
  });
});

describe('landmask', () => {
  it('decodes a plausible planet: some land, mostly ocean, empty poles', () => {
    const dots = landDots();

    expect(dots.length).toBeGreaterThan(2000);
    expect(dots.length).toBeLessThan(MASK_COLS * MASK_ROWS * 0.5);

    // The south polar rows come from a gazetteer with no cities there.
    for (let col = 0; col < MASK_COLS; col++) {
      expect(isLandCell(MASK_ROWS - 1, col)).toBe(false);
    }
  });

  it('knows land from ocean at well-known coordinates', () => {
    const cellFor = (lat: number, lng: number) => {
      const col = Math.floor(((lng + 180) / 360) * MASK_COLS);
      const row = Math.floor(((90 - lat) / 180) * MASK_ROWS);
      return isLandCell(row, col);
    };

    expect(cellFor(52.37, 4.89)).toBe(true); // Amsterdam
    expect(cellFor(35.68, 139.69)).toBe(true); // Tokyo
    expect(cellFor(-23.55, -46.63)).toBe(true); // Sao Paulo
    expect(cellFor(0, -140)).toBe(false); // middle of the Pacific
    expect(cellFor(45, -40)).toBe(false); // middle of the Atlantic
  });

  it('is safely out of bounds', () => {
    expect(isLandCell(-1, 0)).toBe(false);
    expect(isLandCell(0, MASK_COLS)).toBe(false);
    expect(isLandCell(MASK_ROWS, 0)).toBe(false);
  });
});

describe('latLngToUnitVector', () => {
  it('returns unit vectors', () => {
    for (const [lat, lng] of [
      [0, 0],
      [52.37, 4.89],
      [-90, 0],
      [90, 180],
    ]) {
      const [x, y, z] = latLngToUnitVector(lat, lng);
      expect(Math.hypot(x, y, z)).toBeCloseTo(1, 10);
    }
  });

  it('sends the poles to the y axis', () => {
    const [, yn] = latLngToUnitVector(90, 0);
    const [, ys] = latLngToUnitVector(-90, 0);
    expect(yn).toBeCloseTo(1, 10);
    expect(ys).toBeCloseTo(-1, 10);
  });

  it('keeps east and west apart', () => {
    const tokyo = latLngToUnitVector(35.68, 139.69);
    const saoPaulo = latLngToUnitVector(-23.55, -46.63);
    const distance = Math.hypot(tokyo[0] - saoPaulo[0], tokyo[1] - saoPaulo[1], tokyo[2] - saoPaulo[2]);
    // Nearly antipodal cities must land nearly 2 apart on the unit sphere.
    expect(distance).toBeGreaterThan(1.7);
  });
});
