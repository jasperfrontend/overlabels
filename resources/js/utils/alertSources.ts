/**
 * Where an alert's source (head, html, css) comes from when it fires.
 *
 * The overlay loads the source of every alert that can fire on it at mount
 * (`alert_sources` in the render payload). AlertTriggered carries the alert's
 * slug and a content-hash version, and the source itself only while the
 * broadcast stays under Reverb's 10 KB ceiling. So, in order:
 *
 *  1. The source rode the broadcast (`source_omitted` is not true): use it.
 *     It is the server's copy at fire time, so it is never stale, and the
 *     overlay remembers it.
 *  2. The mount-time copy has the same version: use it.
 *  3. Otherwise (alert created or edited after the overlay loaded, and too big
 *     to inline): fetch it by slug.
 *
 * A broadcast with no version is from a server that predates this and always
 * inlines; a preload hit with no version to compare against is accepted.
 */

export interface AlertSource {
  head: string;
  html: string;
  css: string;
  version: string;
}

export type AlertSourceMap = Record<string, AlertSource>;

export type AlertSourcePlan = { kind: 'ready'; source: AlertSource; remember: boolean } | { kind: 'fetch'; slug: string } | { kind: 'none' };

const str = (v: unknown): string => (typeof v === 'string' ? v : '');

/** One source from untrusted JSON, or null when it has no html to render. */
export function toAlertSource(raw: unknown): AlertSource | null {
  if (!raw || typeof raw !== 'object') return null;
  const r = raw as Record<string, unknown>;
  if (typeof r.html !== 'string') return null;
  return { head: str(r.head), html: r.html, css: str(r.css), version: str(r.version) };
}

/** The render payload's `alert_sources`, dropping anything malformed rather than throwing. */
export function toAlertSourceMap(raw: unknown): AlertSourceMap {
  const out: AlertSourceMap = {};
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return out;
  for (const [slug, value] of Object.entries(raw as Record<string, unknown>)) {
    const source = toAlertSource(value);
    if (source) out[slug] = source;
  }
  return out;
}

export function planAlertSource(map: AlertSourceMap, alert: Record<string, unknown>): AlertSourcePlan {
  const slug = typeof alert.alert_template_slug === 'string' && alert.alert_template_slug !== '' ? alert.alert_template_slug : null;
  const version = typeof alert.alert_template_version === 'string' ? alert.alert_template_version : null;

  // `source_omitted` marks a broadcast that left the source out (sent as empty
  // strings so an older renderer does not throw on null).
  if (typeof alert.html === 'string' && alert.source_omitted !== true) {
    return {
      kind: 'ready',
      source: { head: str(alert.head), html: alert.html, css: str(alert.css), version: version ?? '' },
      remember: slug !== null && version !== null,
    };
  }

  if (slug === null) return { kind: 'none' };

  const known = map[slug];
  if (known && (version === null || known.version === version)) {
    return { kind: 'ready', source: known, remember: false };
  }

  return { kind: 'fetch', slug };
}

/** The stylesheet URLs an alert's head links, so the overlay can warm them at mount. */
export function headStylesheetHrefs(head: string): string[] {
  const hrefs: string[] = [];
  for (const [tag] of head.matchAll(/<link\b[^>]*>/gi)) {
    if (!/\brel\s*=\s*["']?stylesheet\b/i.test(tag)) continue;
    const href = /\bhref\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i.exec(tag);
    const url = href?.[1] ?? href?.[2] ?? href?.[3];
    if (url && /^https:\/\//i.test(url)) hrefs.push(url.replace(/&amp;/g, '&'));
  }
  return hrefs;
}
