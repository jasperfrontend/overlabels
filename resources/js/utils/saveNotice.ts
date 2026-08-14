/**
 * A one-shot toast handed from the page that saved to the page it navigates to.
 *
 * /builder creates an overlay with an axios POST and then leaves for the new
 * template, so it cannot report the outcome itself - the component unmounts
 * before anyone could read a toast. A Laravel session flash cannot carry it
 * either: the controls-import POST runs between the save and the navigation,
 * and flash data survives exactly one request, so it would be aged out before
 * the destination ever renders.
 *
 * sessionStorage rather than localStorage because a notice belongs to this tab
 * and this navigation. A leftover in localStorage - written by a save whose
 * navigation never happened - would surface days later on an unrelated visit,
 * announcing something that did not just occur.
 */

const KEY = 'overlabels:save-notice';

export interface SaveNotice {
  message: string;
  type: 'info' | 'success' | 'warning' | 'error';
}

export function stashSaveNotice(notice: SaveNotice): void {
  try {
    sessionStorage.setItem(KEY, JSON.stringify(notice));
  } catch {
    // Private browsing and quota failures cost a toast, never a save.
  }
}

/**
 * Read and clear in one move, so a notice is shown once and a reload of the
 * destination does not repeat it.
 */
export function takeSaveNotice(): SaveNotice | null {
  try {
    const raw = sessionStorage.getItem(KEY);
    if (!raw) return null;
    sessionStorage.removeItem(KEY);

    const parsed: unknown = JSON.parse(raw);

    return typeof parsed === 'object' && parsed !== null && typeof (parsed as SaveNotice).message === 'string' ? (parsed as SaveNotice) : null;
  } catch {
    return null;
  }
}
