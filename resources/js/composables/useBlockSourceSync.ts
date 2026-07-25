import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import type { BuilderControlDef, useBuilderState } from '@/composables/useBuilderState';

/**
 * "Refresh from source" for the Builder editor: detects placements whose
 * snapshot has drifted from the current block source and lets the user re-take
 * the snapshot in place (same instance_id, position, and size).
 *
 * Editor-scoped by design. A saved overlay never changes until its owner
 * saves it - this only touches the editing session, so the snapshot-copy
 * invariant (blocks are never dereferenced at render) stays fully intact.
 *
 * Drift detection is a plain string comparison against a freshly fetched
 * snapshot: ground truth, no timestamps to store, and it works retroactively
 * on composed overlays saved before this feature existed. Checks run on mount
 * and on window focus (throttled), covering the edit-the-block-in-another-tab
 * round trip.
 */

interface BlockSource {
  name: string;
  snapshot: { head: string; html: string; css: string };
  controls: BuilderControlDef[];
}

interface SnapshotPayload {
  name: string;
  head?: string | null;
  html?: string | null;
  css?: string | null;
  controls?: BuilderControlDef[];
}

const RECHECK_MIN_MS = 10_000;

export function useBlockSourceSync(state: ReturnType<typeof useBuilderState>) {
  // instance_ids whose snapshot differs from the current source.
  const stalePlacementIds = ref<Set<string>>(new Set());

  // Latest fetched source per block id - refresh applies from this cache, so
  // detecting and refreshing can never disagree about what "latest" means.
  const sources = new Map<number, BlockSource>();
  let lastCheck = 0;

  const norm = (value: string | null | undefined) => value ?? '';

  function differs(a: { head?: string | null; html?: string | null; css?: string | null }, b: BlockSource['snapshot']): boolean {
    return norm(a.head) !== b.head || norm(a.html) !== b.html || norm(a.css) !== b.css;
  }

  function recompute(): void {
    const stale = new Set<string>();
    for (const p of state.placements.value) {
      const src = sources.get(p.block_template_id);
      if (src && differs(p.snapshot, src.snapshot)) stale.add(p.instance_id);
    }
    stalePlacementIds.value = stale;
  }

  async function check(): Promise<void> {
    lastCheck = Date.now();
    const ids = [...new Set(state.placements.value.map((p) => p.block_template_id))];
    if (ids.length === 0) {
      stalePlacementIds.value = new Set();
      return;
    }

    const results = await Promise.allSettled(ids.map((id) => axios.get(route('templates.blocks.snapshot', id))));
    results.forEach((result, i) => {
      // A deleted or newly-private source is skipped silently: the placement
      // keeps rendering its snapshot - the invariant working as intended.
      if (result.status !== 'fulfilled') return;
      noteFreshSource(ids[i], result.value.data as SnapshotPayload);
    });
    recompute();
  }

  // Cache a source fetched elsewhere (block picker, this checker). A block
  // placed this session is fresh by definition - caching its payload prevents
  // an older background fetch from flagging it, or a refresh from silently
  // downgrading it.
  function noteFreshSource(blockId: number, data: SnapshotPayload): void {
    sources.set(blockId, {
      name: data.name,
      snapshot: { head: norm(data.head), html: norm(data.html), css: norm(data.css) },
      controls: data.controls ?? [],
    });
    recompute();
  }

  /** Re-take one placement's snapshot from the cached source. True when it changed. */
  function refreshPlacement(instanceId: string): boolean {
    const placement = state.placements.value.find((p) => p.instance_id === instanceId);
    if (!placement) return false;
    const src = sources.get(placement.block_template_id);
    if (!src || !differs(placement.snapshot, src.snapshot)) return false;
    state.refreshSnapshot(instanceId, src);
    recompute();
    return true;
  }

  /** Refresh every stale placement. Returns how many were refreshed. */
  function syncAll(): number {
    let refreshed = 0;
    for (const id of [...stalePlacementIds.value]) {
      if (refreshPlacement(id)) refreshed++;
    }
    return refreshed;
  }

  function onWindowFocus(): void {
    if (Date.now() - lastCheck < RECHECK_MIN_MS) return;
    void check();
  }

  onMounted(() => {
    window.addEventListener('focus', onWindowFocus);
    void check();
  });
  onBeforeUnmount(() => window.removeEventListener('focus', onWindowFocus));

  return { stalePlacementIds, refreshPlacement, syncAll, noteFreshSource };
}
