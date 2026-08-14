import debounce from 'lodash/debounce';
import { ref, watch } from 'vue';

/** Filter values as they arrive from the server, all optional. */
export interface EventFiltersShape {
  search?: string;
  source?: string;
  event_type?: string;
  range?: string;
}

/** Filter values once defaulted, safe to bind straight to inputs. */
export interface NormalizedEventFilters {
  search: string;
  source: string;
  event_type: string;
  range: string;
}

export function normalizeEventFilters(input?: EventFiltersShape): NormalizedEventFilters {
  return {
    search: input?.search || '',
    source: input?.source || '',
    event_type: input?.event_type || '',
    range: input?.range || 'all',
  };
}

/**
 * Local filter state for an Inertia-backed event feed, kept in sync with the
 * server's copy without letting that copy stomp what is being typed.
 *
 * Only for pages whose filters round-trip through props. The token-authed feed
 * owns its filter state outright, never receives an echo, and so has nothing to
 * guard against - it deliberately does not use this.
 *
 * @param options.serverFilters reactive getter for the server's copy, e.g. `() => props.filters`
 * @param options.apply performs the actual visit; called after the dispatched term is recorded
 * @param options.debounceMs quiet period before search-as-you-type fires
 */
export function useEventFilters(options: { serverFilters: () => EventFiltersShape | undefined; apply: () => void; debounceMs?: number }) {
  const filters = ref(normalizeEventFilters(options.serverFilters()));

  // The search term we last asked the server for. A response can only ever echo
  // something we already knew, so writing its `search` back into the box would
  // undo whatever has been typed since the request left - the network is always
  // at least one round trip behind the keyboard, and characters typed while a
  // request is in flight would disappear a beat after appearing. Anything that
  // does NOT match this is news (back/forward, a shared link), so we take it.
  let dispatchedSearch = filters.value.search;

  watch(
    options.serverFilters,
    (incomingRaw) => {
      const incoming = normalizeEventFilters(incomingRaw);
      const isOwnEcho = incoming.search === dispatchedSearch;

      filters.value = {
        ...incoming,
        // The dropdowns can't be mid-edit, so they always take the server's word.
        search: isOwnEcho ? filters.value.search : incoming.search,
      };

      if (!isOwnEcho) dispatchedSearch = incoming.search;
    },
    { deep: true },
  );

  function applyFilter() {
    dispatchedSearch = filters.value.search;
    options.apply();
  }

  const debounceSearch = debounce(applyFilter, options.debounceMs ?? 300);

  /**
   * Offered from the empty state, where the filter panel may be collapsed and
   * the search box out of sight. Cancels any pending debounce so the cleared
   * value cannot be followed by a stale one.
   */
  function clearSearch() {
    debounceSearch.cancel();
    filters.value.search = '';
    applyFilter();
  }

  return { filters, applyFilter, debounceSearch, clearSearch };
}
