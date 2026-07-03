// Per-device preference for which event types the viewer has hidden from their
// activity feed. Subtractive: we store the HIDDEN set, so anything not listed
// stays visible and event types added later show by default. Shared by the
// dashboard events page and the token phone feed so both read/write one key.

const STORAGE_KEY = 'overlabels:hidden-event-types';

export function loadHiddenTypes(): string[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((t): t is string => typeof t === 'string');
  } catch {
    // Storage unavailable (private mode) or corrupt value - treat as "nothing hidden".
    return [];
  }
}

export function saveHiddenTypes(types: string[]): void {
  try {
    const unique = [...new Set(types)];
    if (unique.length === 0) localStorage.removeItem(STORAGE_KEY);
    else localStorage.setItem(STORAGE_KEY, JSON.stringify(unique));
  } catch {
    // Ignore storage failures (private mode, quota) - the filter still works
    // for the current session via the in-memory ref.
  }
}
