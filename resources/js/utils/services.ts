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
