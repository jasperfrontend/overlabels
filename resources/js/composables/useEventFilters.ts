import { useSearchFilters } from '@/composables/useSearchFilters';

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
 * The event-feed flavour of useSearchFilters - same echo-guarded state, with
 * the feed pages' shared filter shape (search/source/event_type/range) baked in.
 */
export function useEventFilters(options: { serverFilters: () => EventFiltersShape | undefined; apply: () => void; debounceMs?: number }) {
  return useSearchFilters({
    serverFilters: options.serverFilters,
    normalize: normalizeEventFilters,
    apply: options.apply,
    debounceMs: options.debounceMs,
  });
}
