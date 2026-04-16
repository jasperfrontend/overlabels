/**
 * Canonical display labels for external service sources.
 *
 * Keys match the `service` column on `external_integrations` and the
 * `source` column on `overlay_controls`. Add a new entry here whenever
 * a new service driver is introduced - this is the single source of
 * truth for the frontend.
 */
export const SERVICE_LABELS: Record<string, string> = {
  kofi: 'Ko-fi',
  streamelements: 'StreamElements',
  streamlabs: 'Streamlabs',
  gpslogger: 'GPSLogger',
  twitch: 'Twitch',
  'overlabels-mobile': 'Overlabels Mobile',
};

/**
 * Resolve a service key to its display label, falling back to the raw
 * key if it's not in the map.
 */
export function serviceLabel(source: string): string {
  return SERVICE_LABELS[source] ?? source;
}

/**
 * Fuzzy subsequence matcher: returns true if every character in `needle`
 * appears in `haystack` in order (not necessarily contiguous). Case-
 * insensitive. This is the same matching style as fzf and VS Code's
 * quick-open, which handles things like "kofi" matching "ko-fi" or
 * "overla" matching "Overlabels Mobile" without any curated alias list.
 */
export function fuzzyMatch(needle: string, haystack: string): boolean {
  if (!needle) return true;
  const n = needle.toLowerCase();
  const h = haystack.toLowerCase();
  let i = 0;
  for (let j = 0; j < h.length && i < n.length; j++) {
    if (h[j] === n[i]) i++;
  }
  return i === n.length;
}

/**
 * Haystack text for a service preset: the preset's own label plus the
 * service's display name plus the raw source key. Used by callers that
 * want to fuzzy-search presets against "category + item" in one go.
 * Derived from SERVICE_LABELS — no curated alias list to maintain.
 */
export function presetHaystack(source: string, label: string): string {
  return `${label} ${serviceLabel(source)} ${source}`;
}
