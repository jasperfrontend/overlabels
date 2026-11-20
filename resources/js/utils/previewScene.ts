/**
 * Preview scenes: a moment of a chat game, played into the designer's preview.
 *
 * A game like Chat Tower is driven by the server, not by chat, so the sample
 * chat that sets Plinko moving does nothing for it, and a streamer choosing a
 * "danger" colour would never see the tower in danger. A scene is the answer:
 * the product's manifest declares a few moments (designer.scenes) as the same
 * events the server broadcasts, and the preview frame hands them to its real
 * handlers. Nothing is written, nothing is broadcast, OBS never sees it: it
 * happens in one frame in one browser, exactly like the sample chat.
 *
 * Two shorthands keep a manifest readable: `tower.stack` is a cleared tower
 * plus one `tower.updated` per block, and `tower.topple` is the topple
 * broadcast for a block on top of that stack. Positions, the running `x` (the
 * lean) and logins are derived here, the way the server derives them.
 */

export type SceneEvent =
  | { event: 'control.updated'; key: string; value: string }
  | { event: 'tower.stack'; blocks: SceneBlock[] }
  | { event: 'tower.topple'; name: string; color?: string; offset: number }
  | { event: 'list.updated'; slug: string; items: string[]; last_removed?: string | null; last_removed_at?: number | 'now' | null };

export interface SceneBlock {
  name: string;
  color?: string;
  /** Block widths from the block below, signed, negative is left. */
  offset: number;
  /** A block that took the tower past the all-time record. */
  record?: boolean;
}

/** One call to one of the overlay's own broadcast handlers. */
export type SceneStep =
  | { kind: 'control'; payload: { key: string; value: string; overlay_slug: '' } }
  | { kind: 'tower'; payload: Record<string, unknown> }
  | { kind: 'list'; payload: Record<string, unknown> };

const round = (n: number): string => String(Math.round(n * 100) / 100);

/**
 * The handler calls a scene stands for, in order. Anything malformed is
 * skipped rather than thrown: this runs inside an overlay frame, where an
 * exception has nowhere useful to go.
 */
export function expandScene(events: unknown, nowSeconds: number): SceneStep[] {
  if (!Array.isArray(events)) return [];

  const steps: SceneStep[] = [];
  let top = { position: 0, x: 0 };

  for (const raw of events) {
    if (!raw || typeof raw !== 'object') continue;
    const e = raw as Record<string, unknown>;

    if (e.event === 'control.updated' && typeof e.key === 'string' && e.key !== '') {
      // An empty overlay_slug is how the server marks an account-wide control,
      // and the handler applies that to every overlay, this one included.
      steps.push({ kind: 'control', payload: { key: e.key, value: String(e.value ?? ''), overlay_slug: '' } });
    } else if (e.event === 'tower.stack' && Array.isArray(e.blocks)) {
      steps.push({ kind: 'tower', payload: { cleared: true, height: 0, block: null, toppled: null } });
      top = { position: 0, x: 0 };
      for (const b of e.blocks) {
        const block = sceneBlock(b, top, nowSeconds);
        if (!block) continue;
        top = { position: Number(block.position), x: Number(block.x) };
        steps.push({ kind: 'tower', payload: { cleared: false, height: top.position, block, toppled: null } });
      }
    } else if (e.event === 'tower.topple') {
      const block = sceneBlock(e, top, nowSeconds);
      if (!block) continue;
      steps.push({
        kind: 'tower',
        payload: {
          cleared: true,
          height: 0,
          block,
          toppled: { by: block.name, login: block.login, height: Number(block.position), record_tower: false },
        },
      });
      top = { position: 0, x: 0 };
    } else if (e.event === 'list.updated' && typeof e.slug === 'string' && Array.isArray(e.items)) {
      steps.push({
        kind: 'list',
        payload: {
          slug: e.slug,
          items: e.items.map((item) => String(item)),
          last_removed: typeof e.last_removed === 'string' ? e.last_removed : null,
          last_removed_at: e.last_removed_at === 'now' ? nowSeconds : typeof e.last_removed_at === 'number' ? e.last_removed_at : null,
        },
      });
    }
  }

  return steps;
}

/** The block the server would broadcast for this one, landing on `top`. */
function sceneBlock(raw: unknown, top: { position: number; x: number }, nowSeconds: number): Record<string, string> | null {
  if (!raw || typeof raw !== 'object') return null;
  const b = raw as Record<string, unknown>;
  const offset = Number(b.offset);
  if (typeof b.name !== 'string' || b.name === '' || !Number.isFinite(offset)) return null;

  return {
    name: b.name,
    login: b.name.toLowerCase(),
    color: typeof b.color === 'string' ? b.color : '',
    position: String(top.position + 1),
    offset: round(offset),
    x: round(top.x + offset),
    record: b.record ? '1' : '',
    at: String(nowSeconds),
  };
}
