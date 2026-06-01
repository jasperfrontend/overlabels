// List item shape, mirroring App\Support\ListItems on the PHP side. A List
// item is an object; the per-item timestamp lives inside it (added_at), and
// label/weight/color carry the rich data that richer overlay consumers
// (custom wheels, leaderboards) animate off of.
export interface ListItem {
  id: number;
  value: string;
  added_at: number;
  label: string | null;
  weight: number;
  color: string | null;
}

/**
 * Extract the plain value strings from List item objects. Mirrors
 * App\Support\ListItems::values on the server so the broadcast-update path
 * produces exactly the same value-based tag projection as the initial render.
 * Tolerates raw strings defensively, so it is safe across the transition and
 * for any consumer that still hands us a string array.
 */
export function listItemValues(items: unknown): string[] {
  if (!Array.isArray(items)) return [];
  return items.map((item) =>
    item != null && typeof item === 'object'
      ? String((item as { value?: unknown }).value ?? '')
      : String(item ?? ''),
  );
}
