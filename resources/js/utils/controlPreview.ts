/**
 * Turn control rows (or Builder control definitions) into the `c:`-prefixed
 * entries the render pipeline expects, so a preview resolves control tags the
 * same way the live overlay does.
 *
 * Key derivation mirrors OverlayTemplateController's render query:
 *   source_managed -> 'c:' + broadcastKey()   e.g. c:kofi:donations_received
 *   otherwise      -> 'c:' + key              e.g. c:myname
 * plus the automatic `_at` companion in Unix seconds.
 */

/**
 * The subset of a control this module needs. Builder control definitions carry
 * no id, source or timestamps, so everything after `type` is optional.
 */
export interface PreviewableControl {
    key: string;
    type: string;
    value: string | null;
    config?: Record<string, any> | null;
    source?: string | null;
    source_managed?: boolean;
    created_at?: string;
    updated_at?: string;
}

/**
 * A representative value for one control.
 *
 * Timers are computed rather than read, because their stored `value` is not the
 * number on screen - it is derived from mode/offset/base and the wall clock,
 * and templates pipe the result through `|duration`. Mirrors
 * OverlayControl::resolveTimerDisplayValue().
 *
 * Random-mode numbers deliberately return their stored value rather than
 * rolling a new one: a preview that changes on every re-render reads as broken.
 */
export function controlPreviewValue(ctrl: PreviewableControl): unknown {
    if (ctrl.type === 'timer') {
        const cfg = ctrl.config ?? {};
        const mode = cfg.mode ?? 'countup';
        const offset = Number(cfg.offset_seconds ?? 0);

        if (mode === 'countto') {
            const target = cfg.target_datetime ? new Date(cfg.target_datetime).getTime() : null;
            if (!target) return 0;
            return Math.max(0, Math.floor((target - Date.now()) / 1000));
        }

        let elapsed = offset;
        if (cfg.running && cfg.started_at) {
            elapsed = offset + Math.floor((Date.now() - new Date(cfg.started_at).getTime()) / 1000);
        }

        const base = Number(cfg.base_seconds ?? 0);

        return mode === 'countdown' ? Math.max(0, base - elapsed) : elapsed;
    }

    return ctrl.value ?? '';
}

/** Mirrors OverlayControl::broadcastKey() for the source case. */
function dataKeyFor(ctrl: PreviewableControl): string {
    return ctrl.source_managed && ctrl.source ? `c:${ctrl.source}:${ctrl.key}` : `c:${ctrl.key}`;
}

/**
 * Build the `c:` half of a preview data bag. Later entries win, so callers
 * should pass template-scoped controls after user-scoped ones when a key
 * exists in both.
 */
export function controlsToPreviewData(controls: PreviewableControl[]): Record<string, unknown> {
    const out: Record<string, unknown> = {};

    for (const ctrl of controls) {
        if (!ctrl?.key) continue;

        const dataKey = dataKeyFor(ctrl);
        out[dataKey] = controlPreviewValue(ctrl);

        // Every control carries an automatic `_at` companion in Unix seconds.
        // Builder definitions have no timestamps yet, so theirs stays absent
        // rather than being faked to now().
        const stamp = ctrl.updated_at ?? ctrl.created_at;
        if (stamp) {
            const ms = new Date(stamp).getTime();
            if (!isNaN(ms)) out[`${dataKey}_at`] = String(Math.floor(ms / 1000));
        }
    }

    return out;
}
