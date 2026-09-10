/**
 * Flat `tower.*` slots for the overlay's Chat Tower iterable.
 *
 * The initial window arrives in the render payload (server-sliced to the top
 * TOWER window); after that, `tower.updated` broadcasts carry ONE block plus
 * the authoritative height - never the whole tower, because a tall tower
 * would overflow Reverb's 10 KB payload limit. The client owns the window
 * from then on: append the block, trim from the BOTTOM, so the window is
 * always the top of the tower. `cleared` (a topple, the go-live reset in
 * per_stream mode, the settings-page reset) drops every block first.
 *
 * Pure module, checkinSlots.ts pattern: no Vue, no socket, fully
 * unit-testable. Index 0 = the LOWEST block in the window (bottom-up, the
 * way a tower is drawn), which is the base only while the tower fits.
 */

export const TOWER_SLOT_PREFIX = 'tower.';

export const DEFAULT_TOWER_WINDOW = 50;

// `offset` and `x` are in block-width units (a block is 4 wide), signed,
// negative is left. `x` of the top block is the tower's lean. `record` is
// '1' on a block that took the tower past the all-time record, else ''.
export const BLOCK_FIELDS = ['name', 'login', 'color', 'position', 'offset', 'x', 'record', 'at'] as const;

export type TowerBlock = Record<(typeof BLOCK_FIELDS)[number], string>;

/**
 * The window as shipped in the render payload. An older server that does
 * not send `tower_window` yields NaN, which must fall back to the DEFAULT and
 * never to 1 - the chat clamp lesson.
 */
export function clampTowerWindow(value: unknown): number {
  const n = Math.floor(Number(value));
  if (!Number.isFinite(n) || n < 1) return DEFAULT_TOWER_WINDOW;
  return Math.min(n, DEFAULT_TOWER_WINDOW);
}

/** Normalize a broadcast's block payload; null when it is not a usable block. */
export function toBlock(raw: unknown): TowerBlock | null {
  if (!raw || typeof raw !== 'object') return null;
  const source = raw as Record<string, unknown>;
  if (typeof source.login !== 'string' || source.login === '') return null;
  if (!Number.isFinite(Number(source.position))) return null;

  const block = {} as TowerBlock;
  for (const field of BLOCK_FIELDS) {
    const value = source[field];
    block[field] = typeof value === 'string' ? value : value == null ? '' : String(value);
  }
  return block;
}

/** Read the current window back out of the flat data object. */
export function blocksFromData(data: Record<string, unknown>): TowerBlock[] {
  const blocks: TowerBlock[] = [];
  for (let i = 0; ; i++) {
    if (typeof data[`${TOWER_SLOT_PREFIX}${i}.login`] !== 'string') break;
    const block = {} as TowerBlock;
    for (const field of BLOCK_FIELDS) {
      const value = data[`${TOWER_SLOT_PREFIX}${i}.${field}`];
      block[field] = typeof value === 'string' ? value : '';
    }
    blocks.push(block);
  }
  return blocks;
}

/**
 * A block lands on top: it goes to the END, and the window is trimmed from
 * the FRONT so the top of the tower is what survives. A block whose position
 * is already in the window replaces it (a replayed delta changes nothing).
 */
export function appendBlock(blocks: TowerBlock[], block: TowerBlock, cap: number): TowerBlock[] {
  const rest = blocks.filter((b) => b.position !== block.position);
  const next = [...rest, block].sort((a, b) => Number(a.position) - Number(b.position));
  return next.slice(-Math.max(1, cap));
}

/**
 * How long the fallen tower stays in the data after a topple, so a template
 * can bring the blocks down (a CSS transition keyed on `tower.falling`)
 * before they vanish. Long enough for a tumble, short enough that the next
 * `!stack` never waits on it (a new block arriving inside the hold clears
 * the rubble first).
 */
export const TOPPLE_HOLD_MS = 3000;

/**
 * The extra `tower.*` slots a topple writes next to the window: who placed
 * the last block, how tall it was, and `falling` = '1' while the hold runs.
 */
export function toppleSlots(toppled: unknown, falling: boolean): Record<string, string> {
  const source = toppled && typeof toppled === 'object' ? (toppled as Record<string, unknown>) : {};
  const text = (value: unknown): string => (typeof value === 'string' ? value : value == null ? '' : String(value));

  return {
    toppled_by: text(source.by),
    toppled_login: text(source.login),
    toppled_height: text(source.height),
    toppled_record: source.record_tower ? '1' : '',
    falling: falling ? '1' : '',
  };
}

/**
 * Drop every previous `tower.*` key, then write the given window, the
 * authoritative height and any extra slots. The drop-then-write is what
 * stops a cleared or shrunk window from resurrecting stale blocks (the
 * withChatSlots rule), and it is also why the topple slots have to be
 * passed back in on every write that should keep them.
 */
export function withTowerSlots(
  data: Record<string, unknown>,
  blocks: TowerBlock[],
  height: number,
  extras: Record<string, string> = {},
): Record<string, unknown> {
  const next: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(data)) {
    if (!key.startsWith(TOWER_SLOT_PREFIX)) {
      next[key] = value;
    }
  }

  next[`${TOWER_SLOT_PREFIX}count`] = String(Math.max(0, height));
  blocks.forEach((block, i) => {
    for (const field of BLOCK_FIELDS) {
      next[`${TOWER_SLOT_PREFIX}${i}.${field}`] = block[field];
    }
  });
  for (const [key, value] of Object.entries(extras)) {
    next[`${TOWER_SLOT_PREFIX}${key}`] = value;
  }

  return next;
}

/** The topple slots currently in the data, so a later write can carry them forward. */
export function toppleSlotsFromData(data: Record<string, unknown>): Record<string, string> {
  const keep: Record<string, string> = {};
  for (const key of ['toppled_by', 'toppled_login', 'toppled_height', 'toppled_record']) {
    const value = data[`${TOWER_SLOT_PREFIX}${key}`];
    if (typeof value === 'string' && value !== '') keep[key] = value;
  }
  return keep;
}
