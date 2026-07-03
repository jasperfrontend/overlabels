// Monochrome 4x4 grid icons that identify the source service in the activity
// feed. Shape is the ONLY identity channel: no color, no text baked in (the
// event text sits next to the icon already). This is deliberate for
// accessibility - the icons stay legible in direct sunlight and for anyone
// with a color vision deficiency, because identity is carried entirely by form.
//
// Encoding: each icon is a uint16, one bit per cell of a 4x4 grid.
//   - Bit 15 (MSB) is the top-left cell.
//   - Bits fill left to right, top to bottom, down to bit 0 (LSB, bottom-right).
//   - 1 = filled cell, 0 = empty. A binary literal reads exactly like the grid.
// Cell index i (0..15): row = i >> 2, col = i & 3, on = (bits >> (15 - i)) & 1.
//
// Adding a provider later: pick any unused uint16 whose filled-cell count sits
// in the 4..8 range, then confirm iconDistance() >= 6 against every existing
// icon. Keeping the distance high is what preserves at-a-glance distinctness
// as the set grows.

export interface ProviderIcon {
  bits: number;
  label: string;
}

// Seven distinct gestalts. Provider -> shape assignment is arbitrary; what
// matters is that each provider maps to exactly one constant and every pair
// stays far apart in Hamming distance.
export const PROVIDER_ICONS: Record<string, ProviderIcon> = {
  twitch: { bits: 0x6996, label: 'Twitch' }, // ring
  fourthwall: { bits: 0x9669, label: 'Fourthwall' }, // x
  streamelements: { bits: 0xf00f, label: 'StreamElements' }, // top + bottom bar
  streamlabs: { bits: 0x9999, label: 'StreamLabs' }, // left + right bar
  bmac: { bits: 0xa5a5, label: 'Buy Me a Coffee' }, // checker
  kofi: { bits: 0x00ff, label: 'Ko-fi' }, // solid base
  throne: { bits: 0xcc00, label: 'Throne' }, // corner block
};

// Catch-all for any source without a dedicated icon. A small centered block
// reads as a neutral "event" marker and is clearly none of the edge/spread
// provider glyphs above. In practice the feed only ever carries known sources,
// so this rarely renders.
export const FALLBACK_ICON: ProviderIcon = { bits: 0x0660, label: 'Event' };

export interface IconCell {
  x: number;
  y: number;
  size: number;
}

// SVG scales crisply at any size and inherits the current text color, which
// gives maximum contrast in both light and dark mode with no color logic.
export function iconCells(bits: number, gap = 0.12): IconCell[] {
  const size = 1 - gap;
  const cells: IconCell[] = [];
  for (let i = 0; i < 16; i++) {
    if ((bits >> (15 - i)) & 1) {
      cells.push({ x: (i & 3) + gap / 2, y: (i >> 2) + gap / 2, size });
    }
  }
  return cells;
}

export function providerIcon(source: string): ProviderIcon {
  return PROVIDER_ICONS[source] ?? FALLBACK_ICON;
}

// Hamming distance on filled cells - use when adding a provider to keep every
// pair at-a-glance distinct (reject candidates that come back under 6).
export function iconDistance(a: number, b: number): number {
  let x = a ^ b;
  let d = 0;
  while (x) {
    d += x & 1;
    x >>= 1;
  }
  return d;
}
