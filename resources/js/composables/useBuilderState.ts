import { computed, ref } from 'vue';
import type { BuilderMetadata, BuilderPlacement } from '@/types';

export interface BuilderControlDef {
    key: string;
    label: string | null;
    description: string | null;
    type: string;
    value: string | null;
    config: Record<string, unknown> | null;
    sort_order: number;
}

export const BUILDER_VERSION = 1;
export const GRID_LIMIT = 24;
export const PLACEMENT_LIMIT = 40;

/**
 * Reactive Builder state: the grid, the placements, selection, and the
 * control definitions of blocks placed THIS session (needed for the
 * controls-import call at save time; existing placements' controls were
 * imported when they were first placed).
 */
export function useBuilderState(initial?: BuilderMetadata | null) {
    const grid = ref({ cols: 12, rows: 8, gap: 8, ...(initial?.grid ?? {}) });
    const canvas = ref({ width: 1920, height: 1080, ...(initial?.canvas ?? {}) });
    const placements = ref<BuilderPlacement[]>(initial?.placements ? [...initial.placements] : []);
    const selectedId = ref<string | null>(null);

    // instance_id -> control defs, only for blocks placed this session.
    const sessionControlDefs = new Map<string, BuilderControlDef[]>();

    const selected = computed(() => placements.value.find((p) => p.instance_id === selectedId.value) ?? null);

    function occupied(x: number, y: number, ignoreId?: string): boolean {
        return placements.value.some(
            (p) =>
                p.instance_id !== ignoreId &&
                x >= p.x && x < p.x + p.w &&
                y >= p.y && y < p.y + p.h,
        );
    }

    function fits(x: number, y: number, w: number, h: number, ignoreId?: string): boolean {
        if (x < 1 || y < 1 || w < 1 || h < 1) return false;
        if (x + w - 1 > grid.value.cols || y + h - 1 > grid.value.rows) return false;
        for (let cx = x; cx < x + w; cx++) {
            for (let cy = y; cy < y + h; cy++) {
                if (occupied(cx, cy, ignoreId)) return false;
            }
        }
        return true;
    }

    /** Largest span (up to desired) that fits at the cell; null if the cell itself is taken. */
    function clampSpan(x: number, y: number, desiredW: number, desiredH: number): { w: number; h: number } | null {
        if (occupied(x, y) || x > grid.value.cols || y > grid.value.rows) return null;
        let w = Math.min(desiredW, grid.value.cols - x + 1);
        let h = Math.min(desiredH, grid.value.rows - y + 1);
        while (w >= 1 && !fits(x, y, w, h)) {
            // Shrink the longer axis first until the block fits the free space.
            if (w >= h && w > 1) w--;
            else if (h > 1) h--;
            else return null;
        }
        return { w, h };
    }

    function addPlacement(
        block: { id: number; slug: string; name: string },
        snapshot: { head: string; html: string; css: string },
        controls: BuilderControlDef[],
        x: number,
        y: number,
        desiredW: number,
        desiredH: number,
    ): BuilderPlacement | null {
        if (placements.value.length >= PLACEMENT_LIMIT) return null;
        const span = clampSpan(x, y, desiredW, desiredH);
        if (!span) return null;

        const placement: BuilderPlacement = {
            instance_id: newInstanceId(),
            block_template_id: block.id,
            block_slug: block.slug,
            block_name: block.name,
            x,
            y,
            w: span.w,
            h: span.h,
            snapshot: { head: snapshot.head, html: snapshot.html, css: snapshot.css },
        };
        placements.value = [...placements.value, placement];
        sessionControlDefs.set(placement.instance_id, controls);
        selectedId.value = placement.instance_id;
        return placement;
    }

    function move(id: string, dx: number, dy: number): void {
        const p = placements.value.find((pl) => pl.instance_id === id);
        if (!p) return;
        if (fits(p.x + dx, p.y + dy, p.w, p.h, id)) {
            p.x += dx;
            p.y += dy;
            placements.value = [...placements.value];
        }
    }

    function resize(id: string, dw: number, dh: number): void {
        const p = placements.value.find((pl) => pl.instance_id === id);
        if (!p) return;
        if (fits(p.x, p.y, p.w + dw, p.h + dh, id)) {
            p.w += dw;
            p.h += dh;
            placements.value = [...placements.value];
        }
    }

    function remove(id: string): void {
        placements.value = placements.value.filter((p) => p.instance_id !== id);
        sessionControlDefs.delete(id);
        if (selectedId.value === id) selectedId.value = null;
    }

    /** Shrink the grid safely: placements are clamped into the new bounds. */
    function setGrid(cols: number, rows: number, gap: number): void {
        grid.value = {
            cols: Math.min(Math.max(cols, 1), GRID_LIMIT),
            rows: Math.min(Math.max(rows, 1), GRID_LIMIT),
            gap: Math.min(Math.max(gap, 0), 100),
        };
        for (const p of placements.value) {
            p.x = Math.min(p.x, grid.value.cols);
            p.y = Math.min(p.y, grid.value.rows);
            p.w = Math.min(p.w, grid.value.cols - p.x + 1);
            p.h = Math.min(p.h, grid.value.rows - p.y + 1);
        }
        placements.value = [...placements.value];
    }

    /** Control definitions to import at save time: union across this session's placements, first key wins. */
    function controlsForImport(): BuilderControlDef[] {
        const byKey = new Map<string, BuilderControlDef>();
        for (const p of placements.value) {
            for (const def of sessionControlDefs.get(p.instance_id) ?? []) {
                if (!byKey.has(def.key)) byKey.set(def.key, def);
            }
        }
        return [...byKey.values()];
    }

    function serialize(): BuilderMetadata {
        return {
            version: BUILDER_VERSION,
            grid: { ...grid.value },
            canvas: { ...canvas.value },
            placements: placements.value.map((p) => ({ ...p, snapshot: { ...p.snapshot } })),
        };
    }

    return {
        grid,
        canvas,
        placements,
        selectedId,
        selected,
        occupied,
        clampSpan,
        addPlacement,
        move,
        resize,
        remove,
        setGrid,
        controlsForImport,
        serialize,
    };
}

function newInstanceId(): string {
    return Math.random().toString(36).slice(2, 8);
}
