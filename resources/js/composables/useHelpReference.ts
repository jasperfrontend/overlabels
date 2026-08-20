import { buildHelpSearch, type HelpDoc, type HelpSearch } from '@/utils/helpSearch';
import { ref, shallowRef } from 'vue';

/**
 * The corpus behind the Alt+R palette.
 *
 * This used to `import.meta.glob` every reference markdown file eagerly. Since
 * AppLayout imports ReferencePalette statically, that put the entire 1.2 MB
 * reference corpus in the main chunk of every authenticated page - so anyone
 * who never pressed Alt+R downloaded all of it anyway, and the cost grew with
 * every new doc.
 *
 * It now fetches `/help-index.json`, which the server already publishes, on
 * first open. The index covers tutorials and guides as well as the reference,
 * so the palette answers "where is this documented" rather than only "which tag
 * is this".
 */
export type { HelpDoc };

export const entries = shallowRef<HelpDoc[]>([]);
export const loading = ref(false);
export const failed = ref(false);

let searcher: HelpSearch | null = null;
let inflight: Promise<void> | null = null;

/**
 * Fetch the index once per page load and keep it in module scope. Repeated
 * opens reuse it; concurrent opens share the one request.
 */
export function loadIndex(): Promise<void> {
  if (inflight) return inflight;

  loading.value = true;
  failed.value = false;

  // Ordinary HTTP caching, not `force-cache`. The index is rebuilt on every
  // deploy, and force-cache serves a stored response without revalidating - so
  // a browser that fetched it once would keep answering from a corpus that
  // predates whatever was just published. Revalidation is a 304 on a static
  // file, which is cheap enough to be the right default.
  inflight = fetch('/help-index.json')
    .then((r) => {
      if (!r.ok) throw new Error(`help index: ${r.status}`);
      return r.json();
    })
    .then((data: HelpDoc[]) => {
      entries.value = data;
      searcher = buildHelpSearch(data);
    })
    .catch(() => {
      // The palette shows a "could not load" line. Retry on the next open
      // rather than leaving a rejected promise cached forever.
      failed.value = true;
      inflight = null;
    })
    .finally(() => {
      loading.value = false;
    });

  return inflight;
}

export function useHelpReference() {
  function search(query: string, limit = 30): HelpDoc[] {
    return searcher ? searcher.search(query, limit) : [];
  }

  return { entries, loading, failed, loadIndex, search };
}
