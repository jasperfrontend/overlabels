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
 * Extra search tokens for each service, used to make category-style
 * filtering work in comboboxes. Typing "elem" should match StreamElements,
 * "overla" should match Overlabels Mobile, "kofi" or "ko-fi" both match, etc.
 * These are appended (invisibly) to the text-value that Reka's Combobox
 * filters against.
 */
export const SERVICE_SEARCH_TOKENS: Record<string, string> = {
  kofi: 'Ko-fi kofi',
  streamelements: 'StreamElements stream elements',
  streamlabs: 'Streamlabs stream labs',
  gpslogger: 'GPSLogger gps logger',
  twitch: 'Twitch',
  'overlabels-mobile': 'Overlabels Mobile overlabels mobile',
};

/**
 * Build the searchable text for a preset combobox item: the visible label
 * plus the service's display name and aliases. Used as `:text-value` on
 * `<ComboboxItem>` so "overla" finds every Overlabels Mobile preset even
 * though none of their labels contain the word.
 */
export function presetSearchText(source: string, label: string): string {
  const tokens = SERVICE_SEARCH_TOKENS[source] ?? source;
  return `${label} ${tokens}`;
}
